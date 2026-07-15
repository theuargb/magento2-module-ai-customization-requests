<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductImageUrlResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function resolve(ProductInterface $product, ?int $storeId = null): ?string
    {
        $image = (string)($product->getData('small_image') ?: $product->getData('image'));
        if ($image === '' || $image === 'no_selection') {
            return null;
        }

        $store = $storeId && $storeId > 0
            ? $this->storeManager->getStore($storeId)
            : $this->storeManager->getStore();

        return rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/catalog/product/' . ltrim($image, '/');
    }
}
