<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/** Single definition of "a shopper may see this product in this store view". */
class StorefrontProductGuard
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Visibility $visibility,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    private function isAvailable(Product $product, int $storeId): bool
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();

        return (int)$product->getStatus() === Status::STATUS_ENABLED
            && in_array((int)$product->getVisibility(), $this->visibility->getVisibleInSiteIds(), true)
            && in_array($websiteId, array_map('intval', (array)$product->getWebsiteIds()), true);
    }

    /** @throws NoSuchEntityException When missing or not available on the storefront. */
    public function getBySku(string $sku, int $storeId): Product
    {
        return $this->assertAvailable($this->productRepository->get($sku, false, $storeId), $storeId);
    }

    /** @throws NoSuchEntityException When missing or not available on the storefront. */
    public function getById(int $productId, int $storeId): Product
    {
        return $this->assertAvailable($this->productRepository->getById($productId, false, $storeId), $storeId);
    }

    /** Restricts a product collection to storefront-available products of the store view. */
    public function filterCollection(ProductCollection $collection, int $storeId): void
    {
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());
    }

    private function assertAvailable(ProductInterface $product, int $storeId): Product
    {
        if (!$product instanceof Product || !$this->isAvailable($product, $storeId)) {
            throw new NoSuchEntityException(__('Product "%1" is not available.', $product->getSku()));
        }

        return $product;
    }
}
