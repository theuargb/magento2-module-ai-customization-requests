<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentRunner;
use Perspective\AiCustomizationRequests\Model\Catalog\PageContextResolver;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;

/**
 * One shopper turn: enabled check, validation, authorize, identity, idempotency, persist user message,
 * run the agent. Same client_message_id: return the stored reply, or re-run the agent if the earlier
 * attempt never produced one.
 */
class ChatTurnService
{
    public function __construct(
        private readonly AgentConfig $agentConfig,
        private readonly ChatSessionService $sessionService,
        private readonly ChatSessionRepository $sessionRepository,
        private readonly ChatMessageRepository $messageRepository,
        private readonly ChatIdentity $identity,
        private readonly PageContextResolver $pageContextResolver,
        private readonly AgentRunner $agentRunner,
        private readonly RequestRepository $requestRepository
    ) {
    }

    /** @throws LocalizedException Shopper-safe messages only. */
    public function send(SendTurnCommand $command): TurnResult
    {
        $storeId = $command->storeId;
        if (!$this->agentConfig->isEnabled($storeId)) {
            throw new LocalizedException(__('AI chat is disabled.'));
        }

        $message = trim($command->message);
        $limit = $this->agentConfig->getMaxMessageLength($storeId);
        if ($message === '') {
            throw new LocalizedException(__('Message must contain no more than %1 characters.', $limit));
        }
        if (mb_strlen($message) > $limit) {
            throw new LocalizedException(__('Message must contain no more than %1 characters.', $limit));
        }

        $clientMessageId = trim($command->clientMessageId);
        if ($clientMessageId === '' || strlen($clientMessageId) > 64) {
            throw new LocalizedException(__('The client message id must be between 1 and 64 characters.'));
        }

        $session = $this->sessionService->authorize(
            $command->sessionUid,
            $command->resumeToken,
            $storeId,
            $command->customerId
        );

        $this->identity->attach($session, $command->customerId, $command->guestEmail);
        $this->sessionRepository->save($session);
        if ($session->getCustomerEmail() === null) {
            throw new LocalizedException(__('An email address is required before starting the chat.'));
        }

        $existing = $this->messageRepository->findByClientMessageId($session->getSessionId(), $clientMessageId);
        if ($existing !== null) {
            $reply = $this->messageRepository->findReplyTo($existing);
            if ($reply !== null) {
                return new TurnResult(
                    $session,
                    $existing,
                    $reply,
                    $this->requestRepository->findLatestForSession($session->getSessionId())
                );
            }
            // Earlier attempt never produced a reply: re-run the agent for the stored user message.
            $result = $this->agentRunner->run($session);

            return new TurnResult($session, $existing, $result->reply, $this->latestRequest($session, $result->request));
        }

        $userMessage = $this->messageRepository->create($session, ChatMessage::ROLE_USER);
        $userMessage->setContent($message);
        $userMessage->setClientMessageId($clientMessageId);
        if ($command->selectedProductSku !== null) {
            $userMessage->setProductMentions([new ProductMention($command->selectedProductSku, true)]);
        }
        if ($command->currentPageUrl !== null) {
            $userMessage->setPageContext($this->pageContextResolver->resolve($command->currentPageUrl, $storeId));
        }
        $this->messageRepository->save($userMessage);

        $result = $this->agentRunner->run($session);

        return new TurnResult($session, $userMessage, $result->reply, $this->latestRequest($session, $result->request));
    }

    private function latestRequest(ChatSession $session, ?CustomizationRequest $request): ?CustomizationRequest
    {
        return $request ?? $this->requestRepository->findLatestForSession($session->getSessionId());
    }
}
