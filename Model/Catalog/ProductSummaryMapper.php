<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;

/** Compact product shape returned to the model by catalog tools. */
class ProductSummaryMapper
{
    /** @return array{sku: string, name: string, short_description: string} */
    public function map(ProductInterface $product): array
    {
        return [
            'sku' => (string)$product->getSku(),
            'name' => trim((string)$product->getName()),
            'short_description' => self::plainText((string)$product->getCustomAttribute('short_description')?->getValue()),
        ];
    }

    public static function plainText(string $html): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }
}
