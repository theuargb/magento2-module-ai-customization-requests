<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

class OpenRouterConfig
{
    private const API_KEY = ConfigReader::PREFIX . 'openrouter/api_key';
    private const SITE_URL = ConfigReader::PREFIX . 'openrouter/site_url';
    private const SITE_TITLE = ConfigReader::PREFIX . 'openrouter/site_title';
    private const CHAT_MODEL = ConfigReader::PREFIX . 'agent/chat_model';
    private const EMBEDDING_MODEL = ConfigReader::PREFIX . 'agent/embedding_model';
    private const HTTP_TIMEOUT = ConfigReader::PREFIX . 'agent/http_timeout';

    public function __construct(private readonly ConfigReader $reader)
    {
    }

    public function getApiKey(?int $storeId): string
    {
        return $this->reader->secret(self::API_KEY, $storeId);
    }

    public function getSiteUrl(?int $storeId): string
    {
        return $this->reader->string(self::SITE_URL, $storeId);
    }

    public function getSiteTitle(?int $storeId): string
    {
        return $this->reader->string(self::SITE_TITLE, $storeId);
    }

    public function getChatModel(?int $storeId): string
    {
        return $this->reader->string(self::CHAT_MODEL, $storeId);
    }

    public function getEmbeddingModel(?int $storeId): string
    {
        return $this->reader->string(self::EMBEDDING_MODEL, $storeId);
    }

    public function getHttpTimeout(?int $storeId): int
    {
        return $this->reader->int(self::HTTP_TIMEOUT, $storeId, 1, 300);
    }
}
