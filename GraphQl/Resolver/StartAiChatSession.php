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

class StartAiChatSession implements ResolverInterface
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
        $customerId = (int)$context->getUserId() ?: null;
        $storeId = (int)$this->storeManager->getStore()->getId();
        $customerEmail = $this->customerEmailResolver->get($customerId, $storeId);

        [$session, $resumeToken] = $this->chatSessionService->create(
            $storeId,
            $customerId,
            $customerEmail
        );
        $request = $this->customizationRequestService->getLatestForSession((int)$session->getId());

        return $this->chatSessionService->formatSession($session, $request, $resumeToken, !$customerEmail);
    }
}
