<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Console\Command;

use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\Ga4Client;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\HttpTransport;
use Hans2103\HyvaDashboardGoogle\Model\Google\SearchConsoleClient;
use Hans2103\HyvaDashboardGoogle\Model\Google\TokenProvider;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Checks the service account and every store view's GA4 / Search Console configuration, bypassing caches.
 */
class TestConnection extends Command
{
    private bool $failed = false;

    public function __construct(
        private readonly TokenProvider $tokenProvider,
        private readonly HttpTransport $transport,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hans2103:dashboard-google:test')
            ->setDescription('Test the Google service account and the GA4 / Search Console configuration per store view')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Only test this store view code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->failed = false;

        try {
            $key = $this->tokenProvider->getKey();
            $this->line($output, 'OK', 'Service account key: ' . $key->clientEmail);
        } catch (GoogleApiException $e) {
            $this->line($output, 'FAIL', 'Service account key: ' . $e->getSafeMessage());
            return Command::FAILURE;
        }

        try {
            $this->tokenProvider->getAccessToken();
            $this->line($output, 'OK', 'Access token');
        } catch (GoogleApiException $e) {
            $this->line($output, 'FAIL', 'Access token: ' . $e->getSafeMessage());
            return Command::FAILURE;
        }

        $uncached = $this->transport->withoutCache();
        $ga4 = new Ga4Client($uncached);
        $gsc = new SearchConsoleClient($uncached);
        $onlyStore = $input->getOption('store');

        foreach ($this->storeManager->getStores() as $store) {
            $code = (string)$store->getCode();
            if ($onlyStore !== null && $onlyStore !== $code) {
                continue;
            }
            $storeId = (int)$store->getId();
            $this->checkGa4($output, $ga4, $code, $this->config->getGa4PropertyId($storeId));
            $this->checkGsc($output, $gsc, $code, $this->config->getGscSiteUrl($storeId));
        }

        return $this->failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkGa4(OutputInterface $output, Ga4Client $client, string $code, ?string $propertyId): void
    {
        if ($propertyId === null) {
            $this->line($output, 'SKIP', $code . ' GA4 property: not configured');
            return;
        }

        try {
            $meta = $client->getPropertyMeta($propertyId);
            $client->runReport($propertyId, [
                'dateRanges' => [['startDate' => 'yesterday', 'endDate' => 'yesterday']],
                'metrics' => [['name' => 'sessions']],
                'limit' => 1,
            ]);
            $this->line($output, 'OK', sprintf('%s GA4 property %s (%s, %s)', $code, $propertyId, $meta['timeZone'], $meta['currencyCode']));
        } catch (GoogleApiException $e) {
            $this->line($output, 'FAIL', sprintf('%s GA4 property %s: %s', $code, $propertyId, $e->getSafeMessage()));
        }
    }

    private function checkGsc(OutputInterface $output, SearchConsoleClient $client, string $code, ?string $siteUrl): void
    {
        if ($siteUrl === null) {
            $this->line($output, 'SKIP', $code . ' Search Console: not configured');
            return;
        }

        try {
            $date = date('Y-m-d', strtotime('-7 days'));
            $client->query($siteUrl, ['startDate' => $date, 'endDate' => $date, 'rowLimit' => 1]);
            $this->line($output, 'OK', sprintf('%s Search Console %s', $code, $siteUrl));
        } catch (GoogleApiException $e) {
            $this->line($output, 'FAIL', sprintf('%s Search Console %s: %s', $code, $siteUrl, $e->getSafeMessage()));
        }
    }

    private function line(OutputInterface $output, string $status, string $text): void
    {
        if ($status === 'FAIL') {
            $this->failed = true;
        }
        $output->writeln(sprintf('[%s] %s', $status, $text));
    }
}
