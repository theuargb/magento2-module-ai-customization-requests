<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\OpenSearch\ProductDocumentBuilder;

class GetProductContextTool implements ToolInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductDocumentBuilder $productDocumentBuilder,
        private readonly Visibility $productVisibility
    ) {
    }

    public function getName(): string
    {
        return 'get_product_context';
    }

    public function getDescription(): string
    {
        return 'Returns detailed grounded context for a specific product SKU.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string'],
            ],
            'required' => ['sku'],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $sku = trim((string)($arguments['sku'] ?? ''));
        if ($sku === '') {
            throw new LocalizedException(__('Product SKU is required.'));
        }

        $storeId = (int)$context->getSession()->getData('store_id');
        $product = $this->productRepository->get($sku, false, $storeId ?: 0, true);
        if (
            (int)$product->getStatus() !== Status::STATUS_ENABLED
            || !in_array((int)$product->getVisibility(), $this->productVisibility->getVisibleInSiteIds(), true)
        ) {
            throw new LocalizedException(__('This product is not available.'));
        }

        return $this->productDocumentBuilder->build($product, $storeId ?: null);
    }
}
