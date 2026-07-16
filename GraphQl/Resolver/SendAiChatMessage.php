<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\AiCustomizationRequests\Model\Agent\AgentLoop;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Customer\CustomerEmailResolver;
use Perspective\AiCustomizationRequests\Model\UrlRewrite\PageContextResolver;

class SendAiChatMessage implements ResolverInterface
{
    public function __construct(
        private readonly ModuleConfig $moduleConfig,
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly AgentLoop $agentLoop,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageContextResolver $pageContextResolver,
        private readonly CustomerEmailResolver $customerEmailResolver
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        if (!$this->moduleConfig->isEnabled()) {
            throw new GraphQlInputException(__('AI chat is disabled.'));
        }

        $messageText = trim((string)$args['input']['message']);
        $storeId = (int)$this->storeManager->getStore()->getId();
        if ($messageText === '' || mb_strlen($messageText) > $this->moduleConfig->getMaxMessageLength($storeId)) {
            throw new GraphQlInputException(__('Message must contain no more than %1 characters.', $this->moduleConfig->getMaxMessageLength($storeId)));
        }

        $customerId = (int)$context->getUserId() ?: null;
        $session = $this->chatSessionService->requireAuthorized(
            (string)$args['input']['session_uid'],
            (string)$args['input']['resume_token'],
            $storeId,
            $customerId
        );
        if ($customerId) {
            $this->chatSessionService->attachCustomerEmail($session, $this->customerEmailResolver->get($customerId, $storeId));
        } else {
            $guestEmail = trim((string)($args['input']['guest_email'] ?? ''));
            if ($guestEmail !== '') {
                $this->customerEmailResolver->setGuestEmail($guestEmail, $storeId);
            }
            $cartEmail = $this->customerEmailResolver->get(null, $storeId);
            if (!$cartEmail) {
                throw new GraphQlInputException(__('An email address is required before starting the chat.'));
            }
            $this->chatSessionService->attachCustomerEmail($session, $cartEmail);
        }

        $selectedProductSku = trim((string)($args['input']['selected_product_sku'] ?? ''));
        $pageContext = $this->pageContextResolver->resolve(
            (string)($args['input']['current_page_url'] ?? ''),
            $storeId
        );
        $this->chatSessionService->addMessage(
            $session,
            'user',
            $messageText,
            'text',
            $selectedProductSku === '' ? [] : [[
                'sku' => $selectedProductSku,
                'selected' => true,
            ]],
            null,
            (string)$args['input']['client_message_id'],
            $pageContext
        );
        $result = $this->agentLoop->run($session, $customerId);
        $assistantMessageModel = $this->chatSessionService->addMessage(
            $session,
            'assistant',
            (string)$result['content'],
            'text',
            $result['product_mentions'] ?? [],
            $result['model_name'] ?? null
        );

        $request = $result['request'] ?? $this->customizationRequestService->getLatestForSession((int)$session->getId());
        $formattedSession = $this->chatSessionService->formatSession($session, $request, null, !(bool)$session->getData('customer_email'));

        return [
            'session' => $formattedSession,
            'assistant_message' => $this->chatSessionService->formatMessage($assistantMessageModel),
            'request_uid' => $request?->getData('request_uid'),
        ];
    }
}
