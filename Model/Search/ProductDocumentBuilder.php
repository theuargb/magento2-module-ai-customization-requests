<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Search;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Perspective\AiCustomizationRequests\Model\Catalog\ProductSummaryMapper;

/**
 * Builds the searchable document (without the embedding) for one product.
 * Only meaningful, non-empty fields: name, descriptions (plain text), category names, readable
 * select/multiselect/text/textarea attributes (excluding SEO/media/price/system codes).
 */
class ProductDocumentBuilder
{
    private const EXCLUDED_ATTRIBUTE_CODES = [
        'description', 'short_description', 'meta_description', 'meta_keyword', 'meta_title', 'name',
        'url_key', 'url_path', 'image', 'small_image', 'thumbnail', 'swatch_image', 'media_gallery',
        'price', 'special_price', 'cost', 'tax_class_id', 'weight', 'options_container', 'required_options',
        'has_options', 'gift_message_available', 'custom_layout', 'custom_layout_update_file', 'page_layout',
        'msrp_display_actual_price_type', 'status', 'visibility', 'sku', 'quantity_and_stock_status',
    ];

    private const READABLE_FRONTEND_INPUTS = ['select', 'multiselect', 'text', 'textarea'];

    /**
     * @param array<int, string> $categoryNames category id => name, preloaded by the caller for the batch.
     * @return array{entity_id: int, sku: string, name: string, short_description: string, description: string,
     *     categories: list<string>, attributes_text: string, combined_text: string, content_hash: string}
     */
    public function build(Product $product, array $categoryNames): array
    {
        $name = trim((string)$product->getName());
        $shortDescription = ProductSummaryMapper::plainText((string)$product->getShortDescription());
        $description = ProductSummaryMapper::plainText((string)$product->getDescription());

        $categories = [];
        foreach ($product->getCategoryIds() as $categoryId) {
            $categoryName = trim($categoryNames[(int)$categoryId] ?? '');
            if ($categoryName !== '' && !in_array($categoryName, $categories, true)) {
                $categories[] = $categoryName;
            }
        }

        $attributesText = $this->buildAttributesText($product);

        $combinedParts = array_values(array_filter([
            $name,
            $shortDescription,
            $description,
            implode(', ', $categories),
            $attributesText,
        ], static fn(string $part): bool => $part !== ''));
        $combinedText = implode("\n", $combinedParts);

        return [
            'entity_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => $name,
            'short_description' => $shortDescription,
            'description' => $description,
            'categories' => $categories,
            'attributes_text' => $attributesText,
            'combined_text' => $combinedText,
            'content_hash' => hash('sha256', $combinedText),
        ];
    }

    private function buildAttributesText(Product $product): string
    {
        $parts = [];
        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute instanceof AbstractAttribute
                || in_array($attribute->getAttributeCode(), self::EXCLUDED_ATTRIBUTE_CODES, true)
                || !in_array((string)$attribute->getFrontendInput(), self::READABLE_FRONTEND_INPUTS, true)
            ) {
                continue;
            }

            $value = $this->attributeValue($product, $attribute);
            if ($value === null || $value === []) {
                continue;
            }

            $label = trim((string)$attribute->getStoreLabel());
            if ($label === '') {
                $label = $attribute->getAttributeCode();
            }

            $parts[] = $label . ': ' . implode(', ', $value);
        }

        return implode('; ', $parts);
    }

    /** @return list<string>|null */
    private function attributeValue(Product $product, AbstractAttribute $attribute): ?array
    {
        $frontendInput = (string)$attribute->getFrontendInput();
        if ($frontendInput === 'select' || $frontendInput === 'multiselect') {
            $text = $product->getAttributeText($attribute->getAttributeCode());
            $values = is_array($text) ? $text : [$text];
        } else {
            $values = [$product->getData($attribute->getAttributeCode())];
        }

        $result = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return $result === [] ? null : $result;
    }
}
