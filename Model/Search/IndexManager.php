<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Search;

use Perspective\AiCustomizationRequests\Model\Config\SearchConfig;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;

/**
 * Per-store alias `<index_name>_store_<id>` pointing at one physical index `<alias>_v<YmdHis>`.
 * Partial reindex writes through the alias; full reindex builds a new physical index then swaps.
 */
class IndexManager
{
    public function __construct(
        private readonly OpenSearchClient $client,
        private readonly SearchConfig $config
    ) {
    }

    /** Live alias name for reads and partial writes. */
    public function getAlias(int $storeId): string
    {
        return $this->config->getIndexAlias($storeId);
    }

    /**
     * Makes sure the alias exists (creates a physical index + alias when missing). Indexer-only.
     *
     * @throws ExternalServiceException
     */
    public function ensureAlias(int $storeId): void
    {
        $alias = $this->getAlias($storeId);
        $response = $this->client->request('GET', '/_alias/' . rawurlencode($alias), null, $storeId, [404]);
        if ($response->status !== 404) {
            return;
        }

        $physicalIndex = $this->createPhysicalIndex($storeId);
        $this->client->request('POST', '/_aliases', [
            'actions' => [
                ['add' => ['index' => $physicalIndex, 'alias' => $alias]],
            ],
        ], $storeId);
    }

    /**
     * Creates a new empty physical index with the knn mapping and returns its name.
     * Mapping: entity_id integer, sku keyword, name/short_description/description/attributes_text/combined_text text,
     * categories keyword, content_hash keyword (not indexed), embedding knn_vector(dimension from config).
     *
     * @throws ExternalServiceException
     */
    public function createPhysicalIndex(int $storeId): string
    {
        $physicalIndex = $this->getAlias($storeId) . '_v' . gmdate('YmdHis');

        $this->client->request('PUT', '/' . rawurlencode($physicalIndex), [
            'settings' => [
                'index' => ['knn' => true],
            ],
            'mappings' => [
                'properties' => [
                    'entity_id' => ['type' => 'integer'],
                    'sku' => ['type' => 'keyword'],
                    'name' => ['type' => 'text'],
                    'short_description' => ['type' => 'text'],
                    'description' => ['type' => 'text'],
                    'attributes_text' => ['type' => 'text'],
                    'combined_text' => ['type' => 'text'],
                    'categories' => ['type' => 'keyword'],
                    'content_hash' => ['type' => 'keyword', 'index' => false],
                    'embedding' => [
                        'type' => 'knn_vector',
                        'dimension' => $this->config->getVectorDimensions($storeId),
                    ],
                ],
            ],
        ], $storeId);

        return $physicalIndex;
    }

    /**
     * Atomically points the alias at $physicalIndex (single `_aliases` call) and deletes the
     * physical indices previously behind the alias.
     *
     * @throws ExternalServiceException
     */
    public function swapAlias(int $storeId, string $physicalIndex): void
    {
        $alias = $this->getAlias($storeId);
        $oldIndices = $this->currentIndices($storeId, $alias);

        $actions = [];
        foreach ($oldIndices as $oldIndex) {
            $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];
        }
        $actions[] = ['add' => ['index' => $physicalIndex, 'alias' => $alias]];

        $this->client->request('POST', '/_aliases', ['actions' => $actions], $storeId);

        foreach ($oldIndices as $oldIndex) {
            $this->client->request('DELETE', '/' . rawurlencode($oldIndex), null, $storeId, [404]);
        }
    }

    /** @return list<string> Physical indices behind the alias; empty when the alias does not exist. */
    private function currentIndices(int $storeId, string $alias): array
    {
        $response = $this->client->request('GET', '/_alias/' . rawurlencode($alias), null, $storeId, [404]);
        if ($response->status === 404) {
            return [];
        }

        $indices = [];
        foreach (array_keys($response->body) as $index) {
            if (is_string($index) && ($response->body[$index]['aliases'][$alias] ?? null) !== null) {
                $indices[] = $index;
            }
        }

        return $indices;
    }
}
