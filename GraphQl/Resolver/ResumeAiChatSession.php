<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Customer\CustomerEmailResolver;

class ResumeAiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerEmailResolver $customerEmailResolver
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $input = $args['input'];
        $session = $this->chatSessionService->requireAuthorized(
            (string)$input['session_uid'],
            (string)$input['resume_token'],
            (int)$this->storeManager->getStore()->getId(),
            (int)$context->getUserId() ?: null
        );

        $customerEmail = $this->customerEmailResolver->get(
            (int)$context->getUserId() ?: null,
            (int)$this->storeManager->getStore()->getId()
        );
        $this->chatSessionService->attachCustomerEmail($session, $customerEmail);

        return $this->chatSessionService->formatSession(
            $session,
            $this->customizationRequestService->getLatestForSession((int)$session->getId()),
            null,
            !$customerEmail
        );
    }
}
