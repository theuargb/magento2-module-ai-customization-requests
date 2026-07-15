<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\OpenSearch;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Catalog\ProductFrontendUrlResolver;
use Perspective\AiCustomizationRequests\Model\Catalog\ProductImageUrlResolver;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\OpenRouter\Client as OpenRouterClient;

class ProductRetriever
{
    public function __construct(
        private readonly Client $openSearchClient,
        private readonly OpenRouterClient $openRouterClient,
        private readonly ModuleConfig $moduleConfig,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductFrontendUrlResolver $productFrontendUrlResolver,
        private readonly ProductImageUrlResolver $productImageUrlResolver,
        private readonly Visibility $productVisibility
    ) {
    }

    public function search(string $query, int $limit = 5, ?int $storeId = null): array
    {
        try {
            $this->openSearchClient->ensureIndex($storeId);
            $embedding = $this->openRouterClient->embed(
                $query,
                $this->moduleConfig->getEmbeddingModel($storeId),
                $this->moduleConfig->getVectorDimensions($storeId),
                $storeId
            );

            $response = $this->openSearchClient->search([
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'knn' => [
                                    'embedding' => [
                                        'vector' => $embedding,
                                        'k' => $limit,
                                    ],
                                ],
                            ],
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => ['name^4', 'combined_text^2', 'attributes_text'],
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ], $storeId);

            $hits = $response['hits']['hits'] ?? [];
            if (!$hits) {
                return $this->fallbackSearch($query, $limit, $storeId);
            }

            $productsById = $this->loadProductsByIds(array_map(
                static fn(array $hit): int => (int)(($hit['_source'] ?? [])['entity_id'] ?? 0),
                $hits
            ), $storeId);

            return array_values(array_filter(array_map(function (array $hit) use ($productsById, $storeId): ?array {
                $source = $hit['_source'] ?? [];
                $entityId = (int)($source['entity_id'] ?? 0);
                $product = $productsById[$entityId] ?? null;

                if (!$product) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'sku' => (string)$product->getSku(),
                    'name' => (string)$product->getName(),
                    'url' => $this->productFrontendUrlResolver->resolve($product, $storeId),
                    'image_url' => $this->productImageUrlResolver->resolve($product, $storeId),
                    'short_description' => trim(strip_tags((string)$product->getData('short_description'))),
                    'context_token' => base64_encode((string)$product->getSku()),
                ];
            }, $hits)));
        } catch (LocalizedException) {
            return $this->fallbackSearch($query, $limit, $storeId);
        }
    }

    private function fallbackSearch(string $query, int $limit, ?int $storeId = null): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'small_image', 'image', 'short_description', 'url_key']);
        if ($storeId && $storeId > 0) {
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
        }
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => '%' . $query . '%'],
            ['attribute' => 'description', 'like' => '%' . $query . '%'],
            ['attribute' => 'short_description', 'like' => '%' . $query . '%'],
        ]);
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        $items = [];
        foreach ($collection as $product) {
            $items[] = [
                'entity_id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'url' => $this->productFrontendUrlResolver->resolve($product, $storeId),
                'image_url' => $this->productImageUrlResolver->resolve($product, $storeId),
                'short_description' => trim(strip_tags((string)$product->getData('short_description'))),
                'context_token' => base64_encode((string)$product->getSku()),
            ];
        }

        return $items;
    }

    /**
     * @param int[] $ids
     * @return array<int, ProductInterface>
     */
    private function loadProductsByIds(array $ids, ?int $storeId = null): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'small_image', 'image', 'short_description', 'url_key']);
        if ($storeId && $storeId > 0) {
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
        }
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addIdFilter($ids);

        $productsById = [];
        foreach ($collection as $product) {
            $productsById[(int)$product->getId()] = $product;
        }

        return $productsById;
    }
}
