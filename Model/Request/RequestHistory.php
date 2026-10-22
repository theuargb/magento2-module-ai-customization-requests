<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\Model\AbstractModel;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory as RequestHistoryResource;

class RequestHistory extends AbstractModel
{
    public const ACTOR_AGENT = 'agent';
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_CUSTOMER = 'customer';

    protected function _construct(): void
    {
        $this->_init(RequestHistoryResource::class);
    }

    /** @param string $actorType One of self::ACTOR_* */
    public function record(int $requestId, string $actorType, ?int $actorId, string $action, ?string $note): void
    {
        $this->setData([
            'request_id' => $requestId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
        ]);
    }

    public function getActorType(): string
    {
        return (string)$this->getData('actor_type');
    }

    public function getActorId(): ?int
    {
        $id = $this->getData('actor_id');

        return $id === null ? null : (int)$id;
    }

    public function getAction(): string
    {
        return (string)$this->getData('action');
    }

    public function getNote(): ?string
    {
        $note = (string)$this->getData('note');

        return $note === '' ? null : $note;
    }

    public function getCreatedAt(): string
    {
        return (string)$this->getData('created_at');
    }
}
