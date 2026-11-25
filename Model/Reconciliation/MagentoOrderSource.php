<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Reconciliation;

use Hans2103\HyvaDashboardGoogle\Model\Period;
use Magento\Framework\App\ResourceConnection;

/**
 * Loads the Magento orders of one store view in a period, for comparison with GA4.
 */
class MagentoOrderSource
{
    public const REVENUE_COLUMNS = [
        'grand_total' => 'grand_total',
        'subtotal_incl_tax' => 'subtotal_incl_tax',
        'subtotal' => 'subtotal',
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    /**
     * @param list<string> $statuses
     * @return list<array{increment_id: string, entity_id: int, created_at: string, amount: float, payment_method: string}>
     */
    public function getOrders(int $storeId, Period $period, array $statuses, bool $storefrontOnly, string $revenueBasis): array
    {
        if ($statuses === []) {
            return [];
        }

        $column = self::REVENUE_COLUMNS[$revenueBasis] ?? self::REVENUE_COLUMNS['grand_total'];
        $connection = $this->resourceConnection->getConnection('sales');

        $select = $connection->select()
            ->from(
                ['o' => $this->resourceConnection->getTableName('sales_order', 'sales')],
                [
                    'increment_id' => 'o.increment_id',
                    'entity_id' => 'o.entity_id',
                    'created_at' => 'o.created_at',
                    'amount' => $connection->getIfNullSql('o.' . $column, '0'),
                ]
            )
            ->joinLeft(
                ['p' => $this->resourceConnection->getTableName('sales_order_payment', 'sales')],
                'p.parent_id = o.entity_id',
                ['payment_method' => $connection->getIfNullSql('p.method', "''")]
            )
            ->where('o.store_id = ?', $storeId)
            ->where('o.status IN (?)', $statuses)
            ->where('o.created_at >= ?', $period->getUtcStart())
            ->where('o.created_at < ?', $period->getUtcEnd())
            ->order('o.created_at DESC');

        if ($storefrontOnly) {
            $select->where("o.remote_ip IS NOT NULL AND o.remote_ip <> ''");
        }

        $orders = [];
        foreach ($connection->fetchAll($select) as $row) {
            $orders[] = [
                'increment_id' => (string)$row['increment_id'],
                'entity_id' => (int)$row['entity_id'],
                'created_at' => (string)$row['created_at'],
                'amount' => (float)$row['amount'],
                'payment_method' => (string)$row['payment_method'],
            ];
        }

        return $orders;
    }
}
