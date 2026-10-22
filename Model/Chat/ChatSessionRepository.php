<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession as ChatSessionResource;

class ChatSessionRepository
{
    public function __construct(
        private readonly ChatSessionFactory $sessionFactory,
        private readonly ChatSessionResource $sessionResource
    ) {
    }

    public function create(): ChatSession
    {
        return $this->sessionFactory->create();
    }

    public function save(ChatSession $session): void
    {
        $this->sessionResource->save($session);
    }

    public function getByUid(string $uid): ?ChatSession
    {
        $session = $this->sessionFactory->create();
        $this->sessionResource->load($session, $uid, 'session_uid');

        return $session->getId() ? $session : null;
    }

    /** @return int Number of deleted sessions; messages cascade, requests keep a null session. */
    public function deleteExpired(): int
    {
        return $this->sessionResource->getConnection()->delete(
            $this->sessionResource->getMainTable(),
            ['expires_at < ?' => gmdate('Y-m-d H:i:s')]
        );
    }
}
