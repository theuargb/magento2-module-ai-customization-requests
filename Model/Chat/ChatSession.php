<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Magento\Framework\Model\AbstractModel;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession as ChatSessionResource;

class ChatSession extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ChatSessionResource::class);
    }

    public function initialize(string $uid, int $storeId, ?int $customerId, string $resumeTokenHash, int $ttlHours): void
    {
        $this->setData([
            'session_uid' => $uid,
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'resume_token_hash' => $resumeTokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttlHours * 3600),
        ]);
    }

    public function getSessionId(): int
    {
        return (int)$this->getId();
    }

    public function getUid(): string
    {
        return (string)$this->getData('session_uid');
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function getCustomerId(): ?int
    {
        $id = $this->getData('customer_id');

        return $id === null ? null : (int)$id;
    }

    public function getCustomerEmail(): ?string
    {
        $email = (string)$this->getData('customer_email');

        return $email === '' ? null : $email;
    }

    public function setCustomerEmail(string $email): void
    {
        $this->setData('customer_email', $email);
    }

    public function getResumeTokenHash(): string
    {
        return (string)$this->getData('resume_token_hash');
    }

    public function isExpired(): bool
    {
        return strtotime((string)$this->getData('expires_at') . ' UTC') < time();
    }
}
