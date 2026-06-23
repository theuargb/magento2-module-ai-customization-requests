<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Workflow\ProductDraftService;

class CreateProductDraftTool implements ToolInterface
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly ProductDraftService $productDraftService
    ) {
    }

    public function getName(): string
    {
        return 'create_product_draft';
    }

    public function getDescription(): string
    {
        return 'Creates an internal draft product derived from the approved customization request data.';
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
        $request = $this->productDraftService->createForRequest($request);
        $context->setRequest($request);

        return [
            'request_uid' => $request->getData('request_uid'),
            'draft_product_id' => (int)$request->getData('draft_product_id'),
            'draft_product_sku' => (string)$request->getData('draft_product_sku'),
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
