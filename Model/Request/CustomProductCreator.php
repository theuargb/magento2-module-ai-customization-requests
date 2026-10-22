<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Creates (or finds, by SKU) the hidden simple product that represents an approved customization.
 */
class CustomProductCreator
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFactory $productFactory,
        private readonly CustomSkuPolicy $skuPolicy
    ) {
    }

    /**
     * @throws LocalizedException Base product missing or not a simple product.
     */
    public function create(CustomizationRequest $request): ProductInterface
    {
        $sku = $this->skuPolicy->forRequest($request);
        try {
            return $this->productRepository->get($sku, false, 0);
        } catch (NoSuchEntityException) {
            // fall through and create it
        }

        try {
            /** @var \Magento\Catalog\Model\Product $base */
            $base = $this->productRepository->getById((int)$request->getBaseProductId(), false, 0);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(
                __('The base product for this request no longer exists.'),
                $exception
            );
        }

        if ($base->getTypeId() !== Type::TYPE_SIMPLE) {
            throw new LocalizedException(
                __('Only simple products can be customized. Product "%1" is a "%2" product.', $base->getSku(), $base->getTypeId())
            );
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productFactory->create();
        $product->setData('sku', $sku);
        $product->setData('type_id', Type::TYPE_SIMPLE);
        $product->setData('attribute_set_id', (int)$base->getAttributeSetId());
        $product->setData('category_ids', (array)$base->getCategoryIds());
        $product->setData('website_ids', (array)$base->getWebsiteIds());
        $product->setData('name', $request->getTitle());
        $product->setData('price', (float)$base->getPrice());
        $product->setData('tax_class_id', $base->getTaxClassId());
        $product->setData('visibility', Visibility::VISIBILITY_NOT_VISIBLE);
        $product->setData('status', Status::STATUS_ENABLED);
        $product->setData(
            'description',
            trim((string)$base->getDescription() . "\n\n" . $request->getSummary())
        );
        $product->setData('stock_data', ['is_in_stock' => 1, 'manage_stock' => 0]);

        try {
            return $this->productRepository->save($product);
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('The custom product could not be created. Details were logged.'),
                $exception
            );
        }
    }
}
