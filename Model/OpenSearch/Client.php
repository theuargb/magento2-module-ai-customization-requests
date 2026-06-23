<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\OpenSearch;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;

class Client
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly ModuleConfig $moduleConfig,
        private readonly Json $json
    ) {
    }

    public function ensureIndex(?int $storeId = null): void
    {
        $indexName = $this->moduleConfig->getOpenSearchIndexName($storeId);
        if ($this->indexExists($indexName, $storeId)) {
            return;
        }

        $body = [
            'settings' => [
                'index' => [
                    'knn' => true,
                ],
            ],
            'mappings' => [
                'properties' => [
                    'entity_id' => ['type' => 'integer'],
                    'sku' => ['type' => 'keyword'],
                    'name' => ['type' => 'text'],
                    'url' => ['type' => 'keyword', 'index' => false],
                    'short_description' => ['type' => 'text'],
                    'description' => ['type' => 'text'],
                    'categories' => ['type' => 'keyword'],
                    'attributes_text' => ['type' => 'text'],
                    'combined_text' => ['type' => 'text'],
                    'embedding' => [
                        'type' => 'knn_vector',
                        'dimension' => $this->moduleConfig->getVectorDimensions($storeId),
                    ],
                ],
            ],
        ];

        $this->request('PUT', '/' . rawurlencode($indexName), $body, $storeId);
    }

    public function recreateIndex(?int $storeId = null): void
    {
        $indexName = $this->moduleConfig->getOpenSearchIndexName($storeId);
        $this->request('DELETE', '/' . rawurlencode($indexName), null, $storeId, [404]);
        $this->ensureIndex($storeId);
    }

    public function bulkUpsert(array $documents, ?int $storeId = null): void
    {
        if (!$documents) {
            return;
        }

        $indexName = $this->moduleConfig->getOpenSearchIndexName($storeId);
        $lines = [];
        foreach ($documents as $document) {
            $lines[] = $this->json->serialize([
                'index' => [
                    '_index' => $indexName,
                    '_id' => (string)$document['entity_id'],
                ],
            ]);
            $lines[] = $this->json->serialize($document);
        }

        $response = $this->requestRaw('POST', '/_bulk', implode("\n", $lines) . "\n", $storeId);
        if (!empty($response['errors'])) {
            throw new LocalizedException(__('OpenSearch bulk index request returned item errors.'));
        }
    }

    public function deleteDocument(string $documentId, ?int $storeId = null): void
    {
        $indexName = $this->moduleConfig->getOpenSearchIndexName($storeId);
        $this->request('DELETE', '/' . rawurlencode($indexName) . '/_doc/' . rawurlencode($documentId), null, $storeId, [404]);
    }

    public function search(array $query, ?int $storeId = null): array
    {
        $indexName = $this->moduleConfig->getOpenSearchIndexName($storeId);
        return $this->request('POST', '/' . rawurlencode($indexName) . '/_search', $query, $storeId);
    }

    private function indexExists(string $indexName, ?int $storeId = null): bool
    {
        try {
            $this->request('GET', '/' . rawurlencode($indexName), null, $storeId);
        } catch (LocalizedException) {
            return false;
        }

        return true;
    }

    private function request(
        string $method,
        string $path,
        ?array $body,
        ?int $storeId = null,
        array $allowedErrorCodes = []
    ): array {
        $payload = $body !== null ? $this->json->serialize($body) : null;
        return $this->requestRaw($method, $path, $payload, $storeId, $allowedErrorCodes);
    }

    private function requestRaw(
        string $method,
        string $path,
        ?string $body,
        ?int $storeId = null,
        array $allowedErrorCodes = []
    ): array {
        $baseUrl = $this->moduleConfig->getOpenSearchBaseUrl($storeId);
        if ($baseUrl === '') {
            throw new LocalizedException(__('OpenSearch host is not configured.'));
        }

        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->moduleConfig->getHttpTimeout($storeId));
        $curl->setHeaders([
            'Content-Type' => 'application/json',
        ]);

        $username = $this->moduleConfig->getOpenSearchUsername($storeId);
        $password = $this->moduleConfig->getOpenSearchPassword($storeId);
        if ($username !== '' || $password !== '') {
            $curl->setCredentials($username, $password);
        }

        $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            $curl->post($baseUrl . $path, $body);
        } else {
            $curl->get($baseUrl . $path);
        }

        $status = (int)$curl->getStatus();
        $responseBody = $curl->getBody();
        $decoded = [];
        if ($responseBody !== '') {
            try {
                $decoded = $this->json->unserialize($responseBody);
            } catch (\InvalidArgumentException) {
                $decoded = [];
            }
        }

        if ($status >= 400 && !in_array($status, $allowedErrorCodes, true)) {
            $message = $decoded['error']['reason'] ?? $decoded['message'] ?? __('OpenSearch request failed.');
            throw new LocalizedException(__('%1', $message));
        }

        return is_array($decoded) ? $decoded : [];
    }
}
