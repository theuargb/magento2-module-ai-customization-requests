<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class ModuleConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
    }

    public function testBuildsHttpOpenSearchUrlFromHostAndPort(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['perspective_ai_customization/opensearch/host', 'stores', null, 'search.internal'],
            ['perspective_ai_customization/opensearch/port', 'stores', null, '9200'],
        ]);

        $config = new ModuleConfig($this->scopeConfig, $this->encryptor);

        self::assertSame('http://search.internal:9200', $config->getOpenSearchBaseUrl());
    }

    public function testPreservesSchemeWhenHostAlreadyContainsIt(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['perspective_ai_customization/opensearch/host', 'stores', null, 'https://search.internal'],
            ['perspective_ai_customization/opensearch/port', 'stores', null, '443'],
        ]);

        $config = new ModuleConfig($this->scopeConfig, $this->encryptor);

        self::assertSame('https://search.internal', $config->getOpenSearchBaseUrl());
    }

    public function testDecryptsOpenRouterApiKey(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['perspective_ai_customization/openrouter/api_key', 'stores', null, '0:3:encrypted-value'],
        ]);
        $this->encryptor->expects(self::once())
            ->method('decrypt')
            ->with('0:3:encrypted-value')
            ->willReturn('sk-or-v1-real-key');

        $config = new ModuleConfig($this->scopeConfig, $this->encryptor);

        self::assertSame('sk-or-v1-real-key', $config->getOpenRouterApiKey());
    }

    public function testReturnsRawSensitiveValueWhenDecryptionFails(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['perspective_ai_customization/openrouter/api_key', 'stores', null, 'plain-text-key'],
        ]);
        $this->encryptor->expects(self::once())
            ->method('decrypt')
            ->with('plain-text-key')
            ->willThrowException(new \RuntimeException('Not encrypted.'));

        $config = new ModuleConfig($this->scopeConfig, $this->encryptor);

        self::assertSame('plain-text-key', $config->getOpenRouterApiKey());
    }

    public function testReturnsEmptyStringWhenEncryptedValueCannotBeDecrypted(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            ['perspective_ai_customization/openrouter/api_key', 'stores', null, '0:3:encrypted-value'],
        ]);
        $this->encryptor->expects(self::once())
            ->method('decrypt')
            ->with('0:3:encrypted-value')
            ->willThrowException(new \RuntimeException('Wrong crypt key.'));

        $config = new ModuleConfig($this->scopeConfig, $this->encryptor);

        self::assertSame('', $config->getOpenRouterApiKey());
    }
}
