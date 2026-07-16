<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class CustomizationProductCreator
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFactory $productFactory
    ) {
    }

    public function create(CustomizationRequest $request): int
    {
        if ($request->getData('custom_product_id')) {
            return (int)$request->getData('custom_product_id');
        }

        $storeId = (int)$request->getData('store_id');
        $baseProduct = $this->productRepository->getById((int)$request->getData('base_product_id'), false, $storeId, true);
        if ($baseProduct->getTypeId() !== Type::TYPE_SIMPLE) {
            throw new LocalizedException(__('Only simple products can be customized.'));
        }

        $customSku = sprintf('%s-CUSTOM-%s', $baseProduct->getSku(), $request->getId());
        try {
            $existingProduct = $this->productRepository->get($customSku, false, $storeId, true);
        } catch (NoSuchEntityException) {
            $existingProduct = null;
        }
        if ($existingProduct) {
            return (int)$existingProduct->getId();
        }

        $product = $this->productFactory->create();
        $product->setTypeId(Type::TYPE_SIMPLE);
        $product->setAttributeSetId((int)$baseProduct->getAttributeSetId());
        $product->setStoreId($storeId);
        $product->setWebsiteIds($baseProduct->getWebsiteIds());
        $product->setCategoryIds($baseProduct->getCategoryIds());
        $product->setSku($customSku);
        $product->setName((string)$request->getData('title'));
        $product->setPrice((float)$baseProduct->getPrice());
        $product->setTaxClassId((int)$baseProduct->getTaxClassId());
        $product->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setData('description', trim(strip_tags((string)$baseProduct->getDescription())) . "\n\n" . (string)$request->getData('summary'));
        $product->setData('short_description', (string)$request->getData('summary'));
        $product->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'is_in_stock' => 1,
            'qty' => 1,
        ]);
        $product = $this->productRepository->save($product);

        return (int)$product->getId();
    }
}
