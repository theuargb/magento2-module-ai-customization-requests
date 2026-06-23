<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage as ChatMessageResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage\CollectionFactory as ChatMessageCollectionFactory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession as ChatSessionResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession\CollectionFactory as ChatSessionCollectionFactory;

class ChatSessionService
{
    public function __construct(
        private readonly ChatSessionFactory $chatSessionFactory,
        private readonly ChatSessionResource $chatSessionResource,
        private readonly ChatSessionCollectionFactory $chatSessionCollectionFactory,
        private readonly ChatMessageFactory $chatMessageFactory,
        private readonly ChatMessageResource $chatMessageResource,
        private readonly ChatMessageCollectionFactory $chatMessageCollectionFactory,
        private readonly Random $random,
        private readonly Json $json
    ) {
    }

    public function getOrCreate(?string $sessionUid, int $storeId, ?int $customerId = null, ?string $customerEmail = null): ChatSession
    {
        if ($sessionUid) {
            $session = $this->getByUid($sessionUid);
            if ($session) {
                if ($customerId && !$session->getData('customer_id')) {
                    $session->setData('customer_id', $customerId);
                }
                if ($customerEmail && !$session->getData('customer_email')) {
                    $session->setData('customer_email', $customerEmail);
                }
                $this->chatSessionResource->save($session);
                return $session;
            }
        }

        /** @var ChatSession $session */
        $session = $this->chatSessionFactory->create();
        $session->setData([
            'session_uid' => bin2hex(random_bytes(16)),
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'visitor_hash' => $this->random->getRandomString(32),
            'status' => 'active',
        ]);
        $this->chatSessionResource->save($session);

        return $session;
    }

    public function getByUid(string $sessionUid): ?ChatSession
    {
        $collection = $this->chatSessionCollectionFactory->create();
        $collection->addFieldToFilter('session_uid', $sessionUid);
        $collection->setPageSize(1);

        $session = $collection->getFirstItem();
        return $session->getId() ? $session : null;
    }

    public function requireByUid(string $sessionUid): ChatSession
    {
        $session = $this->getByUid($sessionUid);
        if (!$session) {
            throw new NoSuchEntityException(__('AI chat session no longer exists.'));
        }

        return $session;
    }

    public function attachCustomerEmail(ChatSession $session, string $email): void
    {
        $session->setData('customer_email', $email);
        $this->chatSessionResource->save($session);
    }

    public function addMessage(
        ChatSession $session,
        string $role,
        string $content,
        string $messageType = 'text',
        array $productMentions = [],
        array $toolCalls = [],
        ?string $modelName = null
    ): ChatMessage {
        /** @var ChatMessage $message */
        $message = $this->chatMessageFactory->create();
        $message->setData([
            'session_id' => (int)$session->getId(),
            'role' => $role,
            'message_type' => $messageType,
            'content' => $content,
            'product_mentions_json' => $productMentions ? $this->json->serialize($productMentions) : null,
            'tool_calls_json' => $toolCalls ? $this->json->serialize($toolCalls) : null,
            'model_name' => $modelName,
        ]);
        $this->chatMessageResource->save($message);

        return $message;
    }

    /**
     * @return ChatMessage[]
     */
    public function getMessages(ChatSession $session): array
    {
        $collection = $this->chatMessageCollectionFactory->create();
        $collection->addFieldToFilter('session_id', (int)$session->getId());
        $collection->setOrder('message_id', 'ASC');

        return $collection->getItems();
    }

    public function formatSession(ChatSession $session, ?CustomizationRequest $request = null): array
    {
        return [
            'session_uid' => (string)$session->getData('session_uid'),
            'customer_email' => $session->getData('customer_email'),
            'request_uid' => $request?->getData('request_uid'),
            'messages' => array_map([$this, 'formatMessage'], $this->getMessages($session)),
        ];
    }

    public function formatMessage(ChatMessage $message): array
    {
        return [
            'role' => (string)$message->getData('role'),
            'content' => (string)$message->getData('content'),
            'message_type' => (string)$message->getData('message_type'),
            'product_mentions' => $this->decodeJsonArray($message->getData('product_mentions_json')),
            'created_at' => (string)$message->getData('created_at'),
        ];
    }

    private function decodeJsonArray(?string $value): array
    {
        if (!$value) {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
