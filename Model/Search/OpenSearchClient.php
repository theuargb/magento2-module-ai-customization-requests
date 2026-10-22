<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Search;

use Perspective\AiCustomizationRequests\Model\Config\SearchConfig;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;
use Perspective\AiCustomizationRequests\Model\Infra\HttpResponse;
use Perspective\AiCustomizationRequests\Model\Infra\JsonCodec;
use Perspective\AiCustomizationRequests\Model\Infra\JsonHttpClient;
use Psr\Log\LoggerInterface;

/**
 * Thin OpenSearch REST wrapper bound to SearchConfig connection settings (service name "opensearch").
 */
class OpenSearchClient
{
    public function __construct(
        private readonly JsonHttpClient $httpClient,
        private readonly SearchConfig $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<mixed>|null $body JSON-encoded when given.
     * @param int[] $acceptedErrorStatuses e.g. [404] for existence checks / idempotent deletes.
     * @throws ExternalServiceException Also when no host is configured.
     */
    public function request(string $method, string $path, ?array $body, int $storeId, array $acceptedErrorStatuses = []): HttpResponse
    {
        $baseUrl = $this->config->getBaseUrl($storeId);
        if ($baseUrl === '') {
            throw new ExternalServiceException('opensearch');
        }

        return $this->httpClient->send(
            'opensearch',
            $method,
            $baseUrl . $path,
            $body !== null ? JsonCodec::encode($body) : null,
            [],
            $this->config->getHttpTimeout($storeId),
            $this->config->getCredentials($storeId),
            $acceptedErrorStatuses
        );
    }

    /**
     * NDJSON bulk request; throws when the response reports item errors (logged with first error reason).
     *
     * @param list<array<mixed>> $lines Action and source lines in order.
     * @throws ExternalServiceException
     */
    public function bulk(array $lines, int $storeId): void
    {
        if ($lines === []) {
            return;
        }

        $ndjson = implode("\n", array_map(JsonCodec::encode(...), $lines)) . "\n";

        $baseUrl = $this->config->getBaseUrl($storeId);
        if ($baseUrl === '') {
            throw new ExternalServiceException('opensearch');
        }

        $response = $this->httpClient->send(
            'opensearch',
            'POST',
            $baseUrl . '/_bulk',
            $ndjson,
            [],
            $this->config->getHttpTimeout($storeId),
            $this->config->getCredentials($storeId)
        );

        if (!empty($response->body['errors'])) {
            $reason = null;
            foreach ((array)($response->body['items'] ?? []) as $item) {
                foreach ($item as $action) {
                    if (is_array($action) && isset($action['error'])) {
                        $reason = is_array($action['error'])
                            ? (string)($action['error']['reason'] ?? json_encode($action['error']))
                            : (string)$action['error'];
                        break 2;
                    }
                }
            }
            $this->logger->error(sprintf('[opensearch] bulk request returned item errors: %s', $reason ?? 'unknown'));
            throw new ExternalServiceException('opensearch');
        }
    }

    /**
     * Runs several searches against one index in a single `_msearch` request.
     *
     * @param list<array<mixed>> $queries Search bodies.
     * @return list<array<mixed>> Hits arrays (`hits.hits`) per query, same order.
     * @throws ExternalServiceException
     */
    public function multiSearch(string $index, array $queries, int $storeId): array
    {
        if ($queries === []) {
            return [];
        }

        $lines = [];
        foreach ($queries as $query) {
            $lines[] = ['index' => $index];
            $lines[] = $query;
        }
        $ndjson = implode("\n", array_map(JsonCodec::encode(...), $lines)) . "\n";

        $baseUrl = $this->config->getBaseUrl($storeId);
        if ($baseUrl === '') {
            throw new ExternalServiceException('opensearch');
        }

        $response = $this->httpClient->send(
            'opensearch',
            'POST',
            $baseUrl . '/_msearch',
            $ndjson,
            [],
            $this->config->getHttpTimeout($storeId),
            $this->config->getCredentials($storeId)
        );

        $results = [];
        foreach ((array)($response->body['responses'] ?? []) as $item) {
            if (!is_array($item) || isset($item['error'])) {
                throw new ExternalServiceException('opensearch');
            }
            $results[] = (array)($item['hits']['hits'] ?? []);
        }
        if (count($results) !== count($queries)) {
            throw new ExternalServiceException('opensearch');
        }

        return $results;
    }
}
