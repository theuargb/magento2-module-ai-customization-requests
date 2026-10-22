<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Catalog;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Perspective\AiCustomizationRequests\Model\Chat\PageContext;

/** Maps the shopper's current storefront URL to a catalog entity through URL rewrites. */
class PageContextResolver
{
    public function __construct(
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
        private readonly StorefrontProductGuard $productGuard
    ) {
    }

    public function resolve(string $pageUrl, int $storeId): ?PageContext
    {
        $requestPath = $this->getRequestPath($pageUrl, $storeId);
        if ($requestPath === null || $requestPath === '') {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID => $storeId,
        ]);
        if ($rewrite === null || !(int)$rewrite->getEntityId() || !$rewrite->getEntityType()) {
            return null;
        }

        $entityType = (string)$rewrite->getEntityType();
        $entityId = (int)$rewrite->getEntityId();
        $sku = null;
        $name = null;
        if ($entityType === 'product') {
            try {
                $product = $this->productGuard->getById($entityId, $storeId);
                $sku = (string)$product->getSku();
                $name = (string)$product->getName();
            } catch (NoSuchEntityException) {
                return null;
            }
        }

        return new PageContext($entityType, $entityId, (string)$rewrite->getRequestPath(), $sku, $name);
    }

    private function getRequestPath(string $pageUrl, int $storeId): ?string
    {
        $page = parse_url($pageUrl);
        $base = parse_url($this->storeManager->getStore($storeId)->getBaseUrl());
        if (!is_array($page) || !is_array($base) || empty($page['host']) || empty($base['host'])
            || strcasecmp((string)$page['host'], (string)$base['host']) !== 0
        ) {
            return null;
        }

        $pagePath = '/' . ltrim((string)($page['path'] ?? ''), '/');
        $basePath = rtrim('/' . trim((string)($base['path'] ?? ''), '/'), '/');
        if ($basePath !== '' && !str_starts_with($pagePath . '/', $basePath . '/')) {
            return null;
        }

        return trim(substr($pagePath, strlen($basePath)), '/');
    }
}
