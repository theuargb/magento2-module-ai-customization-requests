<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

/** OpenSearch connection for the AI index; empty overrides fall back to the catalog search connection. */
class SearchConfig
{
    private const HOST = ConfigReader::PREFIX . 'opensearch/host';
    private const PORT = ConfigReader::PREFIX . 'opensearch/port';
    private const USERNAME = ConfigReader::PREFIX . 'opensearch/username';
    private const PASSWORD = ConfigReader::PREFIX . 'opensearch/password';
    private const INDEX_NAME = ConfigReader::PREFIX . 'opensearch/index_name';
    private const DIMENSIONS = ConfigReader::PREFIX . 'opensearch/vector_dimensions';
    private const BATCH_SIZE = ConfigReader::PREFIX . 'opensearch/batch_size';
    private const HTTP_TIMEOUT = ConfigReader::PREFIX . 'agent/http_timeout';

    private const CATALOG_HOST = 'catalog/search/opensearch_server_hostname';
    private const CATALOG_PORT = 'catalog/search/opensearch_server_port';
    private const CATALOG_AUTH_ENABLED = 'catalog/search/opensearch_enable_auth';
    private const CATALOG_USERNAME = 'catalog/search/opensearch_username';
    private const CATALOG_PASSWORD = 'catalog/search/opensearch_password';

    public function __construct(private readonly ConfigReader $reader)
    {
    }

    /** Base URL without trailing slash, or '' when no host is configured anywhere. */
    public function getBaseUrl(?int $storeId): string
    {
        $host = $this->reader->string(self::HOST, $storeId) ?: $this->reader->string(self::CATALOG_HOST, $storeId);
        if ($host === '') {
            return '';
        }
        $port = (int)($this->reader->string(self::PORT, $storeId) ?: $this->reader->string(self::CATALOG_PORT, $storeId));
        if (preg_match('#^https?://#i', $host) !== 1) {
            $host = ($port === 443 ? 'https://' : 'http://') . $host;
        }
        if ($port > 0 && parse_url($host, PHP_URL_PORT) === null) {
            $host = rtrim($host, '/') . ':' . $port;
        }

        return rtrim($host, '/');
    }

    /** @return array{0: string, 1: string}|null */
    public function getCredentials(?int $storeId): ?array
    {
        $username = $this->reader->string(self::USERNAME, $storeId);
        if ($username !== '') {
            return [$username, $this->reader->secret(self::PASSWORD, $storeId)];
        }
        if (!$this->reader->flag(self::CATALOG_AUTH_ENABLED, $storeId)) {
            return null;
        }

        return [
            $this->reader->string(self::CATALOG_USERNAME, $storeId),
            $this->reader->string(self::CATALOG_PASSWORD, $storeId),
        ];
    }

    /** Alias that always points at the live index of one store view. */
    public function getIndexAlias(int $storeId): string
    {
        return strtolower($this->reader->string(self::INDEX_NAME, $storeId)) . '_store_' . $storeId;
    }

    public function getVectorDimensions(?int $storeId): int
    {
        return $this->reader->int(self::DIMENSIONS, $storeId, 1, 16000);
    }

    public function getBatchSize(?int $storeId): int
    {
        return $this->reader->int(self::BATCH_SIZE, $storeId, 1, 500);
    }

    public function getHttpTimeout(?int $storeId): int
    {
        return $this->reader->int(self::HTTP_TIMEOUT, $storeId, 1, 300);
    }
}
