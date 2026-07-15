<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Indexer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\OpenRouter\Client as OpenRouterClient;
use Perspective\AiCustomizationRequests\Model\OpenSearch\Client as OpenSearchClient;
use Perspective\AiCustomizationRequests\Model\OpenSearch\ProductDocumentBuilder;

class ProductIndexer implements ActionInterface, MviewActionInterface
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductDocumentBuilder $productDocumentBuilder,
        private readonly OpenRouterClient $openRouterClient,
        private readonly OpenSearchClient $openSearchClient,
        private readonly ModuleConfig $moduleConfig,
        private readonly Visibility $productVisibility,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function executeFull(): void
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('entity_id');
        $productIds = array_map('intval', $collection->getAllIds());
        if ($productIds === []) {
            return;
        }

        foreach ($this->getStoreIds() as $storeId) {
            $this->openSearchClient->recreateIndex($storeId);
            foreach (array_chunk($productIds, $this->moduleConfig->getIndexBatchSize($storeId)) as $batch) {
                $this->reindexIds($batch, $storeId);
            }
        }
    }

    public function executeList(array $ids): void
    {
        $normalizedIds = $this->normalizeIds($ids);
        if ($normalizedIds === []) {
            return;
        }

        foreach ($this->getStoreIds() as $storeId) {
            $this->openSearchClient->ensureIndex($storeId);
            $this->reindexIds($normalizedIds, $storeId);
        }
    }

    public function executeRow($id): void
    {
        $this->executeList([(int)$id]);
    }

    public function execute($ids): void
    {
        $this->executeList((array)$ids);
    }

    private function reindexIds(array $productIds, int $storeId): void
    {
        $documents = [];
        foreach ($productIds as $productId) {
            try {
                $product = $this->productRepository->getById((int)$productId, false, $storeId, true);
                if (
                    (int)$product->getStatus() !== Status::STATUS_ENABLED
                    || !in_array((int)$product->getVisibility(), $this->productVisibility->getVisibleInSiteIds(), true)
                ) {
                    $this->openSearchClient->deleteDocument((string)$productId, $storeId);
                    continue;
                }

                $document = $this->productDocumentBuilder->build($product, $storeId ?: null);
                $document['embedding'] = $this->openRouterClient->embed(
                    $document['combined_text'],
                    $this->moduleConfig->getEmbeddingModel($storeId),
                    $this->moduleConfig->getVectorDimensions($storeId),
                    $storeId
                );
                $documents[] = $document;
            } catch (NoSuchEntityException) {
                $this->openSearchClient->deleteDocument((string)$productId, $storeId);
            }
        }

        if ($documents !== []) {
            $this->openSearchClient->bulkUpsert($documents, $storeId);
        }
    }

    private function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    /** @return int[] */
    private function getStoreIds(): array
    {
        $ids = array_map(static fn($store): int => (int)$store->getId(), $this->storeManager->getStores());
        return $ids ?: [(int)$this->storeManager->getDefaultStoreView()->getId()];
    }
}
