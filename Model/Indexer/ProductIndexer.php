<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Indexer;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Catalog\StorefrontProductGuard;
use Perspective\AiCustomizationRequests\Model\Config\SearchConfig;
use Perspective\AiCustomizationRequests\Model\OpenRouter\OpenRouterClient;
use Perspective\AiCustomizationRequests\Model\Search\IndexManager;
use Perspective\AiCustomizationRequests\Model\Search\OpenSearchClient;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;
use Perspective\AiCustomizationRequests\Model\Search\ProductDocumentBuilder;

/**
 * RAG product indexer (id perspective_ai_product_rag), one alias per store view.
 * - executeFull: per store, new physical index, batches of storefront-available products, swap alias.
 * - executeList/Row/mview: per store, ensure alias, upsert available ids, delete the rest of the given ids.
 * Embeddings are reused when the document content_hash matches the live alias document
 * (`_mget` with `_source: [content_hash, embedding]`); new texts are embedded in one batched call.
 */
class ProductIndexer implements ActionInterface, MviewActionInterface
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StorefrontProductGuard $productGuard,
        private readonly ProductDocumentBuilder $documentBuilder,
        private readonly OpenRouterClient $openRouterClient,
        private readonly OpenSearchClient $client,
        private readonly IndexManager $indexManager,
        private readonly SearchConfig $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function executeFull(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            $physicalIndex = $this->indexManager->createPhysicalIndex($storeId);
            $alias = $this->indexManager->getAlias($storeId);
            $batchSize = $this->config->getBatchSize($storeId);

            $collection = $this->collectionFactory($storeId);
            $collection->addAttributeToSelect('*');
            $collection->setPageSize($batchSize);

            $lastPage = max(1, (int)ceil($collection->getSize() / $batchSize));
            for ($page = 1; $page <= $lastPage; $page++) {
                $collection->clear();
                $collection->setCurPage($page);
                $products = array_values(iterator_to_array($collection, false));
                if ($products === []) {
                    break;
                }
                $categoryNames = $this->loadCategoryNames($products, $storeId);
                $this->indexBatch($products, $physicalIndex, $alias, $categoryNames, $storeId);
            }

            $this->indexManager->swapAlias($storeId, $physicalIndex);
        }
    }

    /** @param int[] $ids */
    public function executeList(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            $alias = $this->indexManager->getAlias($storeId);
            $this->indexManager->ensureAlias($storeId);

            $collection = $this->collectionFactory($storeId);
            $collection->addAttributeToSelect('*');
            $collection->addIdFilter($ids);
            $collection->setPageSize(count($ids));

            $products = array_values(iterator_to_array($collection, false));
            $availableIds = array_map(static fn($product): int => (int)$product->getId(), $products);

            if ($products !== []) {
                $categoryNames = $this->loadCategoryNames($products, $storeId);
                $this->indexBatch($products, $alias, $alias, $categoryNames, $storeId);
            }

            $deletableIds = array_values(array_diff($ids, $availableIds));
            if ($deletableIds !== []) {
                $lines = [];
                foreach ($deletableIds as $id) {
                    $lines[] = ['delete' => ['_index' => $alias, '_id' => (string)$id]];
                }
                $this->client->bulk($lines, $storeId);
            }
        }
    }

    /** @param int $id */
    public function executeRow($id): void
    {
        $this->executeList([(int)$id]);
    }

    /** @param int[] $ids Mview changelog ids. */
    public function execute($ids): void
    {
        $this->executeList((array)$ids);
    }

    private function collectionFactory(int $storeId): ProductCollection
    {
        $collection = $this->productCollectionFactory->create();
        $this->productGuard->filterCollection($collection, $storeId);

        return $collection;
    }

    /**
     * Builds, embeds (reusing live-alias embeddings for unchanged content), and bulk-indexes one batch.
     *
     * @param list<\Magento\Catalog\Model\Product> $products
     * @param array<int, string> $categoryNames
     */
    private function indexBatch(array $products, string $targetIndex, string $reuseAlias, array $categoryNames, int $storeId): void
    {
        $documents = [];
        foreach ($products as $product) {
            $documents[(int)$product->getId()] = $this->documentBuilder->build($product, $categoryNames);
        }

        $reusable = $reuseAlias !== '' ? $this->liveEmbeddings(array_keys($documents), $reuseAlias, $storeId) : [];
        $toEmbed = [];

        foreach ($documents as $entityId => $document) {
            $live = $reusable[$entityId] ?? null;
            if ($live !== null && $live['content_hash'] === $document['content_hash']) {
                $documents[$entityId]['embedding'] = $live['embedding'];
            } else {
                $toEmbed[$entityId] = $document['combined_text'];
            }
        }

        if ($toEmbed !== []) {
            $vectors = $this->openRouterClient->embed(
                array_values($toEmbed),
                $this->config->getVectorDimensions($storeId),
                $storeId
            );
            foreach (array_keys($toEmbed) as $i => $entityId) {
                $documents[$entityId]['embedding'] = $vectors[$i] ?? throw new ExternalServiceException('openrouter');
            }
        }

        $lines = [];
        foreach ($documents as $entityId => $document) {
            $lines[] = ['index' => ['_index' => $targetIndex, '_id' => (string)$entityId]];
            $lines[] = $document;
        }
        $this->client->bulk($lines, $storeId);
    }

    /**
     * `_mget` against the live alias; 404-tolerant.
     *
     * @param int[] $entityIds
     * @return array<int, array{content_hash: string, embedding: list<float>}> Present documents keyed by entity_id.
     */
    private function liveEmbeddings(array $entityIds, string $alias, int $storeId): array
    {
        if ($entityIds === []) {
            return [];
        }

        $response = $this->client->request(
            'GET',
            '/' . rawurlencode($alias) . '/_mget',
            [
                'ids' => array_map('strval', $entityIds),
                '_source' => ['content_hash', 'embedding'],
            ],
            $storeId,
            [404]
        );
        if ($response->status === 404) {
            return [];
        }

        $result = [];
        foreach ((array)($response->body['docs'] ?? []) as $doc) {
            if (!is_array($doc) || !($doc['found'] ?? false)) {
                continue;
            }
            $source = $doc['_source'] ?? [];
            if (!is_array($source) || !isset($source['content_hash'], $source['embedding'])) {
                continue;
            }
            $result[(int)($doc['_id'] ?? 0)] = [
                'content_hash' => (string)$source['content_hash'],
                'embedding' => array_map('floatval', (array)$source['embedding']),
            ];
        }

        return $result;
    }

    /**
     * @param list<\Magento\Catalog\Model\Product> $products
     * @return array<int, string> category id => name (loaded once per batch, store-scoped)
     */
    private function loadCategoryNames(array $products, int $storeId): array
    {
        $categoryIds = [];
        foreach ($products as $product) {
            foreach ($product->getCategoryIds() as $categoryId) {
                $categoryIds[(int)$categoryId] = true;
            }
        }
        if ($categoryIds === []) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['in' => array_keys($categoryIds)]);

        $names = [];
        foreach ($collection as $category) {
            $name = trim((string)$category->getName());
            if ($name !== '') {
                $names[(int)$category->getId()] = $name;
            }
        }

        return $names;
    }
}
