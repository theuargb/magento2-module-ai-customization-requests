<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Url as ProductUrl;
use Magento\Store\Model\StoreManagerInterface;

class ProductFrontendUrlResolver
{
    public function __construct(
        private readonly ProductUrl $productUrl,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function resolve(ProductInterface $product, ?int $storeId = null): ?string
    {
        if (!(int)$product->getId()) {
            return null;
        }

        $resolvedStoreId = $this->resolveStoreId($product, $storeId);

        if (method_exists($product, 'setStoreId')) {
            $product->setStoreId($resolvedStoreId);
        }

        return $this->productUrl->getUrl($product, [
            '_scope' => $resolvedStoreId,
            '_scope_to_url' => true,
            '_nosid' => true,
        ]);
    }

    private function resolveStoreId(ProductInterface $product, ?int $storeId): int
    {
        if ($storeId && $storeId > 0) {
            return $storeId;
        }

        $productStoreId = method_exists($product, 'getStoreId') ? (int)$product->getStoreId() : 0;
        if ($productStoreId > 0) {
            return $productStoreId;
        }

        return (int)$this->storeManager->getDefaultStoreView()->getId();
    }
}
