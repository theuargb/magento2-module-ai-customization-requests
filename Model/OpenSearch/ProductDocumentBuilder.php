<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\OpenSearch;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

class ProductDocumentBuilder
{
    private const EXCLUDED_ATTRIBUTE_CODES = [
        'description',
        'short_description',
        'meta_description',
        'meta_keyword',
        'meta_title',
        'name',
        'url_key',
        'url_path',
        'image',
        'small_image',
        'thumbnail',
        'media_gallery',
        'price',
        'special_price',
        'tax_class_id',
        'weight',
        'options_container',
        'required_options',
        'has_options',
        'gift_message_available',
    ];

    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    public function build(ProductInterface $product): array
    {
        $categories = $this->getCategoryNames($product);
        $attributes = $this->extractAttributes($product);
        $description = trim(strip_tags((string)$product->getData('description')));
        $shortDescription = trim(strip_tags((string)$product->getData('short_description')));
        $name = trim((string)$product->getName());

        $combinedTextParts = array_filter([
            $name,
            $shortDescription,
            $description,
            implode(', ', $categories),
            implode('; ', array_map(
                static fn(string $label, mixed $value): string => $label . ': ' . (is_array($value) ? implode(', ', $value) : (string)$value),
                array_keys($attributes),
                array_values($attributes)
            )),
        ]);

        return [
            'entity_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => $name,
            'short_description' => $shortDescription,
            'description' => $description,
            'categories' => $categories,
            'attributes_text' => implode('; ', array_map(
                static fn(string $label, mixed $value): string => $label . ': ' . (is_array($value) ? implode(', ', $value) : (string)$value),
                array_keys($attributes),
                array_values($attributes)
            )),
            'combined_text' => implode("\n", $combinedTextParts),
        ];
    }

    private function getCategoryNames(ProductInterface $product): array
    {
        $categoryIds = $product->getCategoryIds();
        if (!$categoryIds) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['in' => $categoryIds]);

        $names = [];
        foreach ($collection as $category) {
            $name = trim((string)$category->getName());
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function extractAttributes(ProductInterface $product): array
    {
        if (!method_exists($product, 'getAttributes')) {
            return [];
        }

        $result = [];
        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute instanceof AbstractAttribute) {
                continue;
            }

            $code = (string)$attribute->getAttributeCode();
            if (in_array($code, self::EXCLUDED_ATTRIBUTE_CODES, true)) {
                continue;
            }

            $frontendInput = (string)$attribute->getFrontendInput();
            if (!in_array($frontendInput, ['select', 'multiselect', 'text', 'textarea'], true)) {
                continue;
            }

            $value = $frontendInput === 'select' || $frontendInput === 'multiselect'
                ? $product->getAttributeText($code)
                : $product->getData($code);

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $label = (string)$attribute->getStoreLabel() ?: $code;
            if (is_string($value) && strpos($value, ',') !== false && $frontendInput === 'multiselect') {
                $value = array_map('trim', explode(',', $value));
            }

            $result[$label] = $value;
        }

        return $result;
    }
}
