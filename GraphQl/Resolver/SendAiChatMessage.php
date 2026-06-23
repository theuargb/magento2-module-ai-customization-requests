<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\Model\Agent\AgentLoop;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class SendAiChatMessage implements ResolverInterface
{
    public function __construct(
        private readonly ModuleConfig $moduleConfig,
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly AgentLoop $agentLoop,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        if (!$this->moduleConfig->isEnabled()) {
            throw new GraphQlInputException(__('AI chat is disabled.'));
        }

        $messageText = trim((string)$args['input']['message']);
        if ($messageText === '') {
            throw new GraphQlInputException(__('Message cannot be empty.'));
        }

        $session = $this->chatSessionService->requireByUid((string)$args['input']['session_uid']);
        $customerId = (int)$context->getUserId() ?: null;
        if ($customerId && !$session->getData('customer_email')) {
            $this->chatSessionService->attachCustomerEmail($session, $this->customerRepository->getById($customerId)->getEmail());
        }

        $this->chatSessionService->addMessage($session, 'user', $messageText);
        $result = $this->agentLoop->run($session, $customerId);
        $assistantMessageModel = $this->chatSessionService->addMessage(
            $session,
            'assistant',
            (string)$result['content'],
            'text',
            $result['product_mentions'] ?? [],
            [],
            $result['model_name'] ?? null
        );

        $request = $result['request'] ?? $this->customizationRequestService->getLatestForSession((int)$session->getId());
        $formattedSession = $this->chatSessionService->formatSession($session, $request);

        return [
            'session' => $formattedSession,
            'assistant_message' => $this->chatSessionService->formatMessage($assistantMessageModel),
            'request_uid' => $request?->getData('request_uid'),
        ];
    }
}
