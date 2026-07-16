<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Customer\CustomerEmailResolver;
use Perspective\AiCustomizationRequests\Model\Workflow\AdminNotifier;

class CreateCustomizationRequestTool implements ToolInterface
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AdminNotifier $adminNotifier,
        private readonly CustomerEmailResolver $customerEmailResolver
    ) {
    }

    public function getName(): string
    {
        return 'create_customization_request';
    }

    public function getDescription(): string
    {
        return 'Submits the active shopper customization request for administrator review.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'maxLength' => 255],
                'summary' => ['type' => 'string', 'maxLength' => 5000],
                'base_product_sku' => ['type' => 'string'],
                'requested_changes' => ['type' => 'object'],
            ],
            'required' => ['title', 'summary', 'base_product_sku', 'requested_changes'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $email = $this->customerEmailResolver->get(
            $context->getCustomerId(),
            (int)$context->getSession()->getData('store_id')
        );
        $sku = trim((string)($arguments['base_product_sku'] ?? ''));
        $title = trim((string)($arguments['title'] ?? ''));
        $summary = trim((string)($arguments['summary'] ?? ''));
        $changes = $arguments['requested_changes'] ?? null;
        if (!$email || $sku === '' || $title === '' || $summary === '' || !is_array($changes)) {
            throw new LocalizedException(__('A product, request details, and customer email are required.'));
        }

        $product = $this->productRepository->get(
            $sku,
            false,
            (int)$context->getSession()->getData('store_id'),
            true
        );
        $request = $this->customizationRequestService->create(
            $context->getSession(),
            [
                'title' => $title,
                'summary' => $summary,
                'base_product_id' => (int)$product->getId(),
                'base_product_sku' => $sku,
                'requested_changes' => $changes,
            ],
            $context->getCustomerId(),
            $email
        );
        $context->setRequest($request);
        $this->adminNotifier->notifyNewRequest($request);

        return ['request_uid' => (string)$request->getData('request_uid'), 'status' => (string)$request->getData('status')];
    }
}
