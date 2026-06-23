<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class ModuleConfig
{
    private const XML_PATH_ENABLED = 'perspective_ai_customization/general/enabled';
    private const XML_PATH_WIDGET_TITLE = 'perspective_ai_customization/general/widget_title';
    private const XML_PATH_OPENROUTER_API_KEY = 'perspective_ai_customization/openrouter/api_key';
    private const XML_PATH_OPENROUTER_SITE_URL = 'perspective_ai_customization/openrouter/site_url';
    private const XML_PATH_OPENROUTER_SITE_TITLE = 'perspective_ai_customization/openrouter/site_title';
    private const XML_PATH_CHAT_MODEL = 'perspective_ai_customization/agent/chat_model';
    private const XML_PATH_EMBEDDING_MODEL = 'perspective_ai_customization/agent/embedding_model';
    private const XML_PATH_MAX_ITERATIONS = 'perspective_ai_customization/agent/max_iterations';
    private const XML_PATH_HTTP_TIMEOUT = 'perspective_ai_customization/agent/http_timeout';
    private const XML_PATH_OS_HOST = 'perspective_ai_customization/opensearch/host';
    private const XML_PATH_OS_PORT = 'perspective_ai_customization/opensearch/port';
    private const XML_PATH_OS_INDEX = 'perspective_ai_customization/opensearch/index_name';
    private const XML_PATH_OS_USERNAME = 'perspective_ai_customization/opensearch/username';
    private const XML_PATH_OS_PASSWORD = 'perspective_ai_customization/opensearch/password';
    private const XML_PATH_OS_DIMS = 'perspective_ai_customization/opensearch/vector_dimensions';
    private const XML_PATH_OS_BATCH_SIZE = 'perspective_ai_customization/opensearch/batch_size';
    private const XML_PATH_EMAIL_ENABLED = 'perspective_ai_customization/email/enabled';
    private const XML_PATH_EMAIL_SENDER = 'perspective_ai_customization/email/sender_identity';
    private const XML_PATH_EMAIL_APPROVE_TEMPLATE = 'perspective_ai_customization/email/approve_template';
    private const XML_PATH_EMAIL_DECLINE_TEMPLATE = 'perspective_ai_customization/email/decline_template';

    private const XML_PATH_CATALOG_OS_HOST = 'catalog/search/opensearch_server_hostname';
    private const XML_PATH_CATALOG_OS_PORT = 'catalog/search/opensearch_server_port';
    private const XML_PATH_CATALOG_OS_AUTH_ENABLED = 'catalog/search/opensearch_enable_auth';
    private const XML_PATH_CATALOG_OS_USERNAME = 'catalog/search/opensearch_username';
    private const XML_PATH_CATALOG_OS_PASSWORD = 'catalog/search/opensearch_password';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_ENABLED, $storeId);
    }

    public function getWidgetTitle(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_WIDGET_TITLE, $storeId);
    }

    public function getOpenRouterApiKey(?int $storeId = null): string
    {
        return $this->getSensitiveValue(self::XML_PATH_OPENROUTER_API_KEY, $storeId);
    }

    public function getOpenRouterSiteUrl(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OPENROUTER_SITE_URL, $storeId);
    }

    public function getOpenRouterSiteTitle(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OPENROUTER_SITE_TITLE, $storeId);
    }

    public function getChatModel(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_CHAT_MODEL, $storeId);
    }

    public function getEmbeddingModel(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMBEDDING_MODEL, $storeId);
    }

    public function getMaxIterations(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_MAX_ITERATIONS, $storeId));
    }

    public function getHttpTimeout(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_HTTP_TIMEOUT, $storeId));
    }

    public function getOpenSearchHost(?int $storeId = null): string
    {
        $host = (string)$this->getValue(self::XML_PATH_OS_HOST, $storeId);

        return $host !== '' ? $host : (string)$this->getValue(self::XML_PATH_CATALOG_OS_HOST, $storeId);
    }

    public function getOpenSearchPort(?int $storeId = null): int
    {
        $port = (string)$this->getValue(self::XML_PATH_OS_PORT, $storeId);

        return (int)($port !== '' ? $port : $this->getValue(self::XML_PATH_CATALOG_OS_PORT, $storeId));
    }

    public function getOpenSearchIndexName(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_OS_INDEX, $storeId);
    }

    public function getOpenSearchUsername(?int $storeId = null): string
    {
        $username = (string)$this->getValue(self::XML_PATH_OS_USERNAME, $storeId);

        if ($username !== '') {
            return $username;
        }

        return $this->isCatalogOpenSearchAuthEnabled($storeId)
            ? (string)$this->getValue(self::XML_PATH_CATALOG_OS_USERNAME, $storeId)
            : '';
    }

    public function getOpenSearchPassword(?int $storeId = null): string
    {
        $password = $this->getSensitiveValue(self::XML_PATH_OS_PASSWORD, $storeId);

        if ($password !== '') {
            return $password;
        }

        return $this->isCatalogOpenSearchAuthEnabled($storeId)
            ? $this->getSensitiveValue(self::XML_PATH_CATALOG_OS_PASSWORD, $storeId)
            : '';
    }

    public function getVectorDimensions(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_OS_DIMS, $storeId));
    }

    public function getIndexBatchSize(?int $storeId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_OS_BATCH_SIZE, $storeId));
    }

    public function canSendEmails(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_EMAIL_ENABLED, $storeId);
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_SENDER, $storeId);
    }

    public function getApproveTemplate(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_APPROVE_TEMPLATE, $storeId);
    }

    public function getDeclineTemplate(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_DECLINE_TEMPLATE, $storeId);
    }

    public function getOpenSearchBaseUrl(?int $storeId = null): string
    {
        $host = trim($this->getOpenSearchHost($storeId));
        if ($host === '') {
            return '';
        }

        if (preg_match('#^https?://#', $host) !== 1) {
            $scheme = $this->getOpenSearchPort($storeId) === 443 ? 'https://' : 'http://';
            $host = $scheme . $host;
        }

        $parsedPort = parse_url($host, PHP_URL_PORT);
        if ($parsedPort === null && $this->getOpenSearchPort($storeId) > 0) {
            $host .= ':' . $this->getOpenSearchPort($storeId);
        }

        return rtrim($host, '/');
    }

    private function isCatalogOpenSearchAuthEnabled(?int $storeId = null): bool
    {
        return $this->scopeFlag(self::XML_PATH_CATALOG_OS_AUTH_ENABLED, $storeId);
    }

    private function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getSensitiveValue(string $path, ?int $storeId = null): string
    {
        $value = (string)$this->getValue($path, $storeId);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+:\d+:/', $value) !== 1) {
            return $value;
        }

        try {
            $decrypted = $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return '';
        }

        if ($decrypted === '' || mb_detect_encoding($decrypted, 'UTF-8', true) === false) {
            return '';
        }

        return $decrypted;
    }

    private function scopeFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
