<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage as ChatMessageResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage\CollectionFactory;

class ChatMessageRepository
{
    public function __construct(
        private readonly ChatMessageFactory $messageFactory,
        private readonly ChatMessageResource $messageResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function create(ChatSession $session, string $role): ChatMessage
    {
        $message = $this->messageFactory->create();
        $message->setSessionId($session->getSessionId());
        $message->setRole($role);

        return $message;
    }

    public function save(ChatMessage $message): void
    {
        $this->messageResource->save($message);
    }

    /** @return ChatMessage[] Full transcript in insertion order, including tool calls and results. */
    public function getTranscript(int $sessionId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('session_id', $sessionId);
        $collection->setOrder('message_id', 'ASC');

        /** @var ChatMessage[] $messages */
        $messages = $collection->getItems();

        return array_values($messages);
    }

    public function findByClientMessageId(int $sessionId, string $clientMessageId): ?ChatMessage
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('session_id', $sessionId);
        $collection->addFieldToFilter('client_message_id', $clientMessageId);
        $collection->setPageSize(1);
        /** @var ChatMessage $message */
        $message = $collection->getFirstItem();

        return $message->getId() ? $message : null;
    }

    /** Final assistant reply that answers $userMessage, or null when the turn never completed. */
    public function findReplyTo(ChatMessage $userMessage): ?ChatMessage
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('session_id', $userMessage->getSessionId());
        $collection->addFieldToFilter('message_id', ['gt' => $userMessage->getMessageId()]);
        $collection->addFieldToFilter('role', ['neq' => ChatMessage::ROLE_TOOL]);
        $collection->setOrder('message_id', 'ASC');

        /** @var ChatMessage $message */
        foreach ($collection as $message) {
            if ($message->getRole() === ChatMessage::ROLE_USER) {
                return null;
            }
            if ($message->isFinalReply()) {
                return $message;
            }
        }

        return null;
    }
}
