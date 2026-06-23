<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Copier;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class ProductDraftService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Copier $productCopier,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly Json $json
    ) {
    }

    public function createForRequest(CustomizationRequest $request): CustomizationRequest
    {
        if ($request->getData('draft_product_id')) {
            return $request;
        }

        $baseProductId = (int)$request->getData('base_product_id');
        if (!$baseProductId && !$request->getData('base_product_sku')) {
            throw new LocalizedException(__('The request does not reference a base product.'));
        }

        $storeId = (int)$request->getData('store_id');
        $baseProduct = $baseProductId
            ? $this->productRepository->getById($baseProductId, true, $storeId ?: 0, true)
            : $this->productRepository->get((string)$request->getData('base_product_sku'), true, $storeId ?: 0, true);

        $duplicate = $this->productCopier->copy($baseProduct);
        $duplicate->setSku($baseProduct->getSku() . '-AI-' . (int)$request->getId());
        $duplicate->setName($baseProduct->getName() . ' - AI Draft');
        $this->applyDraftCatalogState($duplicate, $baseProduct->getWebsiteIds());

        $requestedChanges = $this->decodeJson((string)$request->getData('requested_changes_json'));
        $this->applySupportedAttributeChanges($duplicate, $requestedChanges);

        $description = trim(strip_tags((string)$duplicate->getData('description')));
        $description .= "\n\nAI customization request:\n" . (($request->getData('summary')) ?: $this->json->serialize($requestedChanges));
        $duplicate->setData('description', nl2br($description));

        $this->productRepository->save($duplicate);

        $request->addData([
            'draft_product_id' => (int)$duplicate->getId(),
            'draft_product_sku' => (string)$duplicate->getSku(),
            'status' => CustomizationRequest::STATUS_PRODUCT_DRAFT_CREATED,
        ]);
        $this->customizationRequestService->save($request);
        $this->customizationRequestService->addHistory(
            $request,
            'agent',
            0,
            'draft_product_created',
            (string)$duplicate->getSku()
        );

        return $request;
    }

    private function applyDraftCatalogState($product, array $websiteIds): void
    {
        $product->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setWebsiteIds($websiteIds);
        $product->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'is_in_stock' => 1,
            'qty' => 1,
        ]);
        $product->setData('quantity_and_stock_status', ['is_in_stock' => 1]);
    }

    private function applySupportedAttributeChanges($product, array $requestedChanges): void
    {
        foreach ($requestedChanges as $key => $value) {
            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $attributeCode = $this->normalizeAttributeCode((string)$key);
            try {
                $attribute = $this->attributeRepository->get('catalog_product', $attributeCode);
            } catch (\Throwable) {
                continue;
            }

            $frontendInput = (string)$attribute->getFrontendInput();
            if ($frontendInput === 'select') {
                foreach ($attribute->getOptions() as $option) {
                    if (strcasecmp(trim((string)$option->getLabel()), trim((string)$value)) === 0) {
                        $product->setData($attributeCode, $option->getValue());
                        continue 2;
                    }
                }
                continue;
            }

            if (in_array($frontendInput, ['text', 'textarea'], true)) {
                $product->setData($attributeCode, (string)$value);
            }
        }
    }

    private function normalizeAttributeCode(string $label): string
    {
        $code = strtolower(trim($label));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?: $code;
        return trim($code, '_');
    }

    private function decodeJson(string $value): array
    {
        if ($value === '') {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
