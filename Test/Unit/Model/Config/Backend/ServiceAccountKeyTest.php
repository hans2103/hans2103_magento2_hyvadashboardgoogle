<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Config\Backend;

use Hans2103\HyvaDashboardGoogle\Model\Config\Backend\ServiceAccountKey;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class ServiceAccountKeyTest extends TestCase
{
    private function backend(string $value): ServiceAccountKey
    {
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(fn (string $v) => 'enc:' . $v);

        /** @var ServiceAccountKey $backend */
        $backend = (new ObjectManager($this))->getObject(ServiceAccountKey::class, ['encryptor' => $encryptor]);
        $backend->setValue($value);

        return $backend;
    }

    public function testValidKeyIsTrimmedAndEncrypted(): void
    {
        $json = TestServiceAccount::json();
        $backend = $this->backend("\n " . $json . " \n");

        $backend->beforeSave();

        $this->assertSame('enc:' . $json, $backend->getValue());
        $this->assertTrue($backend->isSaveAllowed());
    }

    public function testMaskedPlaceholderKeepsStoredValue(): void
    {
        $backend = $this->backend('******');

        $backend->beforeSave();

        $this->assertFalse($backend->isSaveAllowed());
    }

    public function testInvalidKeyIsRejected(): void
    {
        $backend = $this->backend('{"type":"authorized_user"}');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not a valid Google service account JSON key');
        $backend->beforeSave();
    }

    public function testEmptyValueClearsTheKey(): void
    {
        $backend = $this->backend('');

        $backend->beforeSave();

        $this->assertTrue($backend->isSaveAllowed());
        $this->assertSame('', $backend->getValue());
    }
}
