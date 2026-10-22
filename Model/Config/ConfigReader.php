<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/** Store-scoped typed config access shared by the module's *Config classes. */
class ConfigReader
{
    public const PREFIX = 'perspective_ai_customization/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function string(string $path, ?int $storeId): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function int(string $path, ?int $storeId, int $min, int $max): int
    {
        return max($min, min($max, (int)$this->string($path, $storeId)));
    }

    public function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Value saved through the Encrypted backend model. */
    public function secret(string $path, ?int $storeId): string
    {
        $value = $this->string($path, $storeId);

        return $value === '' ? '' : trim($this->encryptor->decrypt($value));
    }
}
