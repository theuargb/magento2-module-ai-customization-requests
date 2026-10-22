<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\ToolResult;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Request\RequestSubmitter;

/**
 * save_customization_request: {base_product_sku, title<=255, summary<=5000, requested_changes object} -> {request_uid, status, created|updated}; result carries the request.
 */
class SaveCustomizationRequestTool implements ToolInterface
{
    public function __construct(private readonly RequestSubmitter $requestSubmitter)
    {
    }

    public function getName(): string
    {
        return 'save_customization_request';
    }

    public function getDescription(): string
    {
        return 'Submits the shopper\'s product customization request for administrator review, or updates '
            . 'the pending request of this chat when one already exists. Call it only after the base '
            . 'product and all requested changes are known.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'base_product_sku' => ['type' => 'string', 'description' => 'SKU of the product to customize.'],
                'title' => ['type' => 'string', 'maxLength' => 255, 'description' => 'Short title of the request.'],
                'summary' => ['type' => 'string', 'maxLength' => 5000, 'description' => 'Summary of the request.'],
                'requested_changes' => [
                    'type' => 'object',
                    'description' => 'Structured list of the concrete changes the shopper asked for.',
                ],
            ],
            'required' => ['base_product_sku', 'title', 'summary', 'requested_changes'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, ChatSession $session): ToolResult
    {
        $sku = trim((string)($arguments['base_product_sku'] ?? ''));
        $title = trim((string)($arguments['title'] ?? ''));
        $summary = trim((string)($arguments['summary'] ?? ''));
        $changes = $arguments['requested_changes'] ?? null;
        if ($sku === '' || $title === '' || $summary === '' || !is_array($changes) || $changes === []) {
            throw new LocalizedException(__('A product, a title, a summary, and the requested changes are required.'));
        }

        $request = $this->requestSubmitter->submit($session, $sku, $title, $summary, $changes);

        return new ToolResult(
            [
                'request_uid' => $request->getUid(),
                'status' => $request->getStatus()->value,
                'title' => $request->getTitle(),
            ],
            [new ProductMention($request->getBaseProductSku())],
            $request
        );
    }
}
