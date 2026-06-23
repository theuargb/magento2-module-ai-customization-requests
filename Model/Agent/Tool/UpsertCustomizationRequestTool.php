<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class UpsertCustomizationRequestTool implements ToolInterface
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly ChatSessionService $chatSessionService,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function getName(): string
    {
        return 'upsert_customization_request';
    }

    public function getDescription(): string
    {
        return 'Creates or updates the active customization request draft from the shopper conversation.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'base_product_sku' => ['type' => 'string'],
                'requested_changes' => ['type' => 'object'],
            ],
            'required' => ['summary', 'requested_changes'],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $customerEmail = $context->getCustomerEmail();
        if (!$customerEmail) {
            throw new LocalizedException(__('Customer email must be collected before creating the request.'));
        }

        $baseProductId = null;
        $baseProductSku = trim((string)($arguments['base_product_sku'] ?? ''));
        if ($baseProductSku !== '') {
            $product = $this->productRepository->get($baseProductSku, false, 0, true);
            $baseProductId = (int)$product->getId();
        }

        $transcript = array_map(
            static fn(array $message): array => [
                'role' => $message['role'],
                'content' => $message['content'],
            ],
            array_map([$this->chatSessionService, 'formatMessage'], $this->chatSessionService->getMessages($context->getSession()))
        );

        $request = $this->customizationRequestService->createOrUpdateDraft(
            $context->getSession(),
            [
                'title' => $arguments['title'] ?? null,
                'summary' => $arguments['summary'] ?? null,
                'base_product_id' => $baseProductId,
                'base_product_sku' => $baseProductSku ?: null,
                'requested_changes' => $arguments['requested_changes'] ?? [],
                'source_transcript' => $transcript,
                'status' => CustomizationRequest::STATUS_NEW,
            ],
            $context->getCustomerId(),
            $customerEmail
        );

        $context->setRequest($request);

        return [
            'request_uid' => $request->getData('request_uid'),
            'status' => $request->getData('status'),
            'base_product_sku' => $request->getData('base_product_sku'),
        ];
    }
}
