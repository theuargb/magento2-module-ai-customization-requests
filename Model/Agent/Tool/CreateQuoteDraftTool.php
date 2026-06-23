<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Workflow\QuoteDraftService;

class CreateQuoteDraftTool implements ToolInterface
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly QuoteDraftService $quoteDraftService
    ) {
    }

    public function getName(): string
    {
        return 'create_quote_draft';
    }

    public function getDescription(): string
    {
        return 'Creates an internal draft Magento quote from the request draft product.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'request_uid' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $request = $this->resolveRequest($arguments, $context);
        $request = $this->quoteDraftService->createForRequest($request);
        $context->setRequest($request);

        return [
            'request_uid' => $request->getData('request_uid'),
            'draft_quote_id' => (int)$request->getData('draft_quote_id'),
            'masked_quote_id' => (string)$request->getData('masked_quote_id'),
            'quote_restore_token' => (string)$request->getData('quote_restore_token'),
            'status' => (string)$request->getData('status'),
        ];
    }

    private function resolveRequest(array $arguments, AgentContext $context)
    {
        $request = $context->getRequest();
        if (!empty($arguments['request_uid'])) {
            $request = $this->customizationRequestService->getByUid((string)$arguments['request_uid']);
        }

        if (!$request || !$request->getId()) {
            throw new LocalizedException(__('Customization request is not available.'));
        }

        return $request;
    }
}
