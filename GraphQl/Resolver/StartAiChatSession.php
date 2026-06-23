<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class StartAiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $customerId = (int)$context->getUserId() ?: null;
        $customerEmail = $args['input']['customer_email'] ?? null;

        if ($customerId) {
            $customerEmail = $this->customerRepository->getById($customerId)->getEmail();
        }

        $session = $this->chatSessionService->getOrCreate(
            $args['input']['session_uid'] ?? null,
            (int)$this->storeManager->getStore()->getId(),
            $customerId,
            $customerEmail
        );
        $request = $this->customizationRequestService->getLatestForSession((int)$session->getId());

        return $this->chatSessionService->formatSession($session, $request);
    }
}
