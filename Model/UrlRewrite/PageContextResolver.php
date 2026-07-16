<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\UrlRewrite;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class PageContextResolver
{
    public function __construct(
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /** @return array<string, int|string>|null */
    public function resolve(string $pageUrl, int $storeId): ?array
    {
        $requestPath = $this->getRequestPath($pageUrl, $storeId);
        if ($requestPath === null || $requestPath === '') {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID => $storeId,
        ]);
        if (!$rewrite || !(int)$rewrite->getEntityId() || !$rewrite->getEntityType()) {
            return null;
        }

        $context = [
            'entity_type' => (string)$rewrite->getEntityType(),
            'entity_id' => (int)$rewrite->getEntityId(),
            'request_path' => (string)$rewrite->getRequestPath(),
        ];
        if ($context['entity_type'] === 'product') {
            try {
                $product = $this->productRepository->getById($context['entity_id'], false, $storeId, true);
                $context['product_sku'] = (string)$product->getSku();
                $context['product_name'] = (string)$product->getName();
            } catch (NoSuchEntityException) {
            }
        }

        return $context;
    }

    private function getRequestPath(string $pageUrl, int $storeId): ?string
    {
        $page = parse_url($pageUrl);
        $base = parse_url($this->storeManager->getStore($storeId)->getBaseUrl());
        if (!is_array($page) || !is_array($base) || empty($page['host']) || empty($base['host'])) {
            return null;
        }

        if (strcasecmp((string)$page['host'], (string)$base['host']) !== 0) {
            return null;
        }

        $pagePath = '/' . ltrim((string)($page['path'] ?? ''), '/');
        $basePath = '/' . trim((string)($base['path'] ?? ''), '/');
        if ($basePath !== '/' && !str_starts_with($pagePath . '/', rtrim($basePath, '/') . '/')) {
            return null;
        }

        return trim(substr($pagePath, strlen(rtrim($basePath, '/'))), '/');
    }
}
