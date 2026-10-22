<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory as RequestHistoryResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory\CollectionFactory;

class RequestHistoryRepository
{
    public function __construct(
        private readonly RequestHistoryFactory $historyFactory,
        private readonly RequestHistoryResource $historyResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /** @param string $actorType One of RequestHistory::ACTOR_* */
    public function add(
        CustomizationRequest $request,
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $note = null
    ): void {
        $history = $this->historyFactory->create();
        $history->record((int)$request->getRequestId(), $actorType, $actorId, $action, $note);
        $this->historyResource->save($history);
    }

    /** @return RequestHistory[] */
    public function getForRequest(int $requestId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('request_id', $requestId);
        $collection->setOrder('history_id', 'ASC');

        /** @var RequestHistory[] $entries */
        $entries = $collection->getItems();

        return array_values($entries);
    }
}
