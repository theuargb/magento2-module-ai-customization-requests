<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Search;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;
use Perspective\AiCustomizationRequests\Model\Catalog\StorefrontProductGuard;
use Perspective\AiCustomizationRequests\Model\Config\SearchConfig;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;
use Perspective\AiCustomizationRequests\Model\OpenRouter\OpenRouterClient;

/**
 * Hybrid retrieval: kNN (query embedding) and BM25 multi_match run in one `_msearch` against the
 * store alias, fused with reciprocal rank fusion (k = 60). Results are loaded through a
 * storefront-filtered collection. When OpenSearch/OpenRouter fail, logs a warning and falls back to a
 * DB LIKE search over name/short_description.
 */
class ProductRetriever
{
    private const RRF_K = 60;

    public function __construct(
        private readonly OpenSearchClient $client,
        private readonly IndexManager $indexManager,
        private readonly OpenRouterClient $openRouterClient,
        private readonly SearchConfig $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly StorefrontProductGuard $productGuard,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return list<Product> Up to $limit storefront-available products, best first, with name and
     *     short_description loaded.
     */
    public function search(string $query, int $limit, int $storeId): array
    {
        try {
            return $this->hybridSearch($query, $limit, $storeId);
        } catch (ExternalServiceException $exception) {
            $this->logger->warning(sprintf(
                '[ai-customization] hybrid search failed, falling back to LIKE search: %s',
                $exception->getMessage()
            ));

            return $this->fallbackSearch($query, $limit, $storeId);
        }
    }

    /**
     * @return list<Product>
     * @throws ExternalServiceException
     */
    private function hybridSearch(string $query, int $limit, int $storeId): array
    {
        $embedding = $this->openRouterClient->embed(
            [$query],
            $this->config->getVectorDimensions($storeId),
            $storeId
        )[0] ?? throw new ExternalServiceException('openrouter');

        $size = $limit * 2;
        $hitsPerQuery = $this->client->multiSearch($this->indexManager->getAlias($storeId), [
            [
                'size' => $size,
                'query' => ['knn' => ['embedding' => ['vector' => $embedding, 'k' => $size]]],
            ],
            [
                'size' => $size,
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['name^4', 'combined_text^2', 'attributes_text'],
                    ],
                ],
            ],
        ], $storeId);

        $scores = [];
        foreach ($hitsPerQuery as $hits) {
            foreach (array_values($hits) as $rank => $hit) {
                $source = $hit['_source'] ?? [];
                if (!is_array($source)) {
                    continue;
                }
                $entityId = (int)($source['entity_id'] ?? 0);
                if ($entityId > 0) {
                    $scores[$entityId] = ($scores[$entityId] ?? 0.0) + 1 / (self::RRF_K + $rank + 1);
                }
            }
        }
        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $productsById = $this->loadProducts(array_keys($scores), $storeId);

        $products = [];
        foreach (array_keys($scores) as $entityId) {
            if (isset($productsById[$entityId])) {
                $products[] = $productsById[$entityId];
                if (count($products) === $limit) {
                    break;
                }
            }
        }

        return $products;
    }

    /**
     * @param int[] $ids
     * @return array<int, Product>
     */
    private function loadProducts(array $ids, int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $this->productGuard->filterCollection($collection, $storeId);
        $collection->addAttributeToSelect(['name', 'short_description']);
        $collection->addIdFilter($ids);
        $collection->setPageSize(count($ids));

        $products = [];
        foreach ($collection as $product) {
            $products[(int)$product->getId()] = $product;
        }

        return $products;
    }

    /** @return list<Product> */
    private function fallbackSearch(string $query, int $limit, int $storeId): array
    {
        $collection = $this->collectionFactory->create();
        $this->productGuard->filterCollection($collection, $storeId);
        $collection->addAttributeToSelect(['name', 'short_description']);
        $escaped = addcslashes($query, "%_\\");
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => '%' . $escaped . '%'],
            ['attribute' => 'short_description', 'like' => '%' . $escaped . '%'],
        ]);
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        return array_values(iterator_to_array($collection, false));
    }
}
