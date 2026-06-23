<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\OpenSearch\ProductRetriever;

class SearchCatalogTool implements ToolInterface
{
    public function __construct(
        private readonly ProductRetriever $productRetriever
    ) {
    }

    public function getName(): string
    {
        return 'search_catalog';
    }

    public function getDescription(): string
    {
        return 'Searches the product catalog using semantic retrieval and returns grounded product candidates.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        $limit = max(1, min(10, (int)($arguments['limit'] ?? 5)));

        return [
            'products' => $this->productRetriever->search(
                $query,
                $limit,
                (int)$context->getSession()->getData('store_id')
            ),
        ];
    }
}
