<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\ToolResult;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Catalog\ProductSummaryMapper;
use Perspective\AiCustomizationRequests\Model\Search\ProductRetriever;

/**
 * search_catalog: {query, limit 1..10 default 5} -> products [sku,name,short_description]; mentions = found SKUs.
 */
class SearchCatalogTool implements ToolInterface
{
    private const DEFAULT_LIMIT = 5;

    public function __construct(
        private readonly ProductRetriever $productRetriever,
        private readonly ProductSummaryMapper $summaryMapper
    ) {
    }

    public function getName(): string
    {
        return 'search_catalog';
    }

    public function getDescription(): string
    {
        return 'Searches the store catalog for products matching a natural-language query and returns '
            . 'the sku, name, and short description of each matching product.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Natural-language product search query.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => self::DEFAULT_LIMIT],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, ChatSession $session): ToolResult
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            throw new LocalizedException(__('The search query is required.'));
        }
        $limit = (int)($arguments['limit'] ?? self::DEFAULT_LIMIT);
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 10) {
            $limit = 10;
        }

        $products = [];
        $mentions = [];
        foreach ($this->productRetriever->search($query, $limit, $session->getStoreId()) as $product) {
            $summary = $this->summaryMapper->map($product);
            $products[] = $summary;
            $mentions[] = new ProductMention($summary['sku']);
        }

        return new ToolResult(['products' => $products], $mentions);
    }
}
