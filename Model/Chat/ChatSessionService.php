<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Infra\TokenService;

/**
 * Session lifecycle and access control. A session is usable only by its store, its owner
 * (customer id or guest=null), with the matching resume token, before expiry.
 */
class ChatSessionService
{
    public function __construct(
        private readonly ChatSessionRepository $sessionRepository,
        private readonly ChatMessageRepository $messageRepository,
        private readonly ChatIdentity $identity,
        private readonly AgentConfig $agentConfig,
        private readonly TokenService $tokenService
    ) {
    }

    public function start(int $storeId, ?int $customerId): StartedSession
    {
        $session = $this->sessionRepository->create();
        $resumeToken = $this->tokenService->secret();
        $session->initialize(
            $this->tokenService->uid(),
            $storeId,
            $customerId,
            $this->tokenService->hash($resumeToken),
            $this->agentConfig->getSessionTtlHours($storeId)
        );
        $this->identity->attach($session, $customerId, null);
        $this->sessionRepository->save($session);

        return new StartedSession($session, $resumeToken);
    }

    /** @throws NoSuchEntityException Generic "not available" for every failure reason. */
    public function authorize(string $sessionUid, string $resumeToken, int $storeId, ?int $customerId): ChatSession
    {
        $session = $this->sessionRepository->getByUid($sessionUid);
        if ($session === null
            || $session->getStoreId() !== $storeId
            || $session->isExpired()
            || $customerId !== $session->getCustomerId()
            || !$this->tokenService->matches($resumeToken, $session->getResumeTokenHash())
        ) {
            throw new NoSuchEntityException(__('This chat session is not available.'));
        }

        return $session;
    }

    /** @return ChatMessage[] User messages and final assistant replies only. */
    public function getVisibleMessages(ChatSession $session): array
    {
        return array_values(array_filter(
            $this->messageRepository->getTranscript($session->getSessionId()),
            static fn(ChatMessage $message): bool => $message->isVisible()
        ));
    }
}
