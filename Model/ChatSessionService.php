<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
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
        private readonly ModuleConfig $moduleConfig,
        private readonly Json $json
    ) {
    }

    /** @return array{0: ChatSession, 1: string} */
    public function create(int $storeId, ?int $customerId = null, ?string $customerEmail = null): array
    {
        $resumeToken = bin2hex(random_bytes(32));
        /** @var ChatSession $session */
        $session = $this->chatSessionFactory->create();
        $session->setData([
            'session_uid' => bin2hex(random_bytes(16)),
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'resume_token_hash' => hash('sha256', $resumeToken),
            'status' => 'active',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $this->moduleConfig->getSessionTtlHours($storeId) * 3600),
        ]);
        $this->chatSessionResource->save($session);

        return [$session, $resumeToken];
    }

    public function getByUid(string $sessionUid): ?ChatSession
    {
        $collection = $this->chatSessionCollectionFactory->create();
        $collection->addFieldToFilter('session_uid', $sessionUid);
        $collection->setPageSize(1);
        $session = $collection->getFirstItem();

        return $session->getId() ? $session : null;
    }

    public function requireAuthorized(string $sessionUid, string $resumeToken, int $storeId, ?int $customerId): ChatSession
    {
        $session = $this->getByUid($sessionUid);
        if (
            !$session
            || (int)$session->getData('store_id') !== $storeId
            || (string)$session->getData('status') !== 'active'
            || strtotime((string)$session->getData('expires_at')) < time()
            || !hash_equals((string)$session->getData('resume_token_hash'), hash('sha256', $resumeToken))
            || ($session->getData('customer_id') && (int)$session->getData('customer_id') !== (int)$customerId)
        ) {
            throw new NoSuchEntityException(__('AI chat session is not available.'));
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
        ?string $modelName = null,
        ?string $clientMessageId = null
    ): ChatMessage {
        /** @var ChatMessage $message */
        $message = $this->chatMessageFactory->create();
        $message->setData([
            'session_id' => (int)$session->getId(),
            'role' => $role,
            'message_type' => $messageType,
            'client_message_id' => $clientMessageId,
            'content' => $content,
            'product_mentions_json' => $productMentions ? $this->json->serialize($productMentions) : null,
            'model_name' => $modelName,
        ]);
        $this->chatMessageResource->save($message);

        return $message;
    }

    /** @return ChatMessage[] */
    public function getMessages(ChatSession $session): array
    {
        $collection = $this->chatMessageCollectionFactory->create();
        $collection->addFieldToFilter('session_id', (int)$session->getId());
        $collection->setOrder('message_id', 'ASC');

        return $collection->getItems();
    }

    public function formatSession(ChatSession $session, ?CustomizationRequest $request = null, ?string $resumeToken = null): array
    {
        return [
            'session_uid' => (string)$session->getData('session_uid'),
            'resume_token' => $resumeToken,
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
            'product_mentions' => array_map(static function (array $mention): array {
                unset($mention['context_token']);
                $mention['selected'] = false;
                return $mention;
            }, $this->decodeJsonArray($message->getData('product_mentions_json'))),
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
