<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\Agent\ToolResult;
use Perspective\AiCustomizationRequests\Model\Catalog\StorefrontProductGuard;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Search\ProductDocumentBuilder;

/**
 * get_product_context: {sku} -> summary + description (plain, capped 4000 chars), categories, readable attributes; available products only; mention = sku.
 */
class GetProductContextTool implements ToolInterface
{
    private const DESCRIPTION_MAX_LENGTH = 4000;

    public function __construct(
        private readonly StorefrontProductGuard $productGuard,
        private readonly ProductDocumentBuilder $documentBuilder,
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    public function getName(): string
    {
        return 'get_product_context';
    }

    public function getDescription(): string
    {
        return 'Returns the full storefront context of one product by SKU: name, short description, '
            . 'description, category names, and readable attribute values. Use it before discussing a '
            . 'product in detail or before submitting a customization request.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string', 'description' => 'SKU of the catalog product.'],
            ],
            'required' => ['sku'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, ChatSession $session): ToolResult
    {
        $sku = trim((string)($arguments['sku'] ?? ''));
        if ($sku === '') {
            throw new LocalizedException(__('A product SKU is required.'));
        }

        $storeId = $session->getStoreId();
        try {
            $product = $this->productGuard->getBySku($sku, $storeId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Product "%1" is not available.', $sku));
        }

        $document = $this->documentBuilder->build($product, $this->getCategoryNames($product, $storeId));

        return new ToolResult(
            [
                'sku' => (string)$document['sku'],
                'name' => (string)$document['name'],
                'short_description' => (string)$document['short_description'],
                'description' => mb_substr((string)$document['description'], 0, self::DESCRIPTION_MAX_LENGTH),
                'categories' => array_values($document['categories']),
                'attributes_text' => $document['attributes_text'],
            ],
            [new ProductMention($sku)]
        );
    }

    /** @return array<int, string> Category id => name. */
    private function getCategoryNames(Product $product, int $storeId): array
    {
        $categoryIds = array_map('intval', (array)$product->getCategoryIds());
        if ($categoryIds === []) {
            return [];
        }

        /** @var CategoryCollection $collection */
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addIdFilter($categoryIds);

        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = (string)$category->getName();
        }

        return $names;
    }
}
