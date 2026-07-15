<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class ResumeAiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly StoreManagerInterface $storeManager
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

        return $this->chatSessionService->formatSession(
            $session,
            $this->customizationRequestService->getLatestForSession((int)$session->getId())
        );
    }
}
