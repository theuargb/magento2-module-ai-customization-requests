<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as CustomizationRequestResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest\CollectionFactory as CustomizationRequestCollectionFactory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory as RequestHistoryResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory\CollectionFactory as RequestHistoryCollectionFactory;

class CustomizationRequestService
{
    public function __construct(
        private readonly CustomizationRequestFactory $requestFactory,
        private readonly CustomizationRequestResource $requestResource,
        private readonly CustomizationRequestCollectionFactory $requestCollectionFactory,
        private readonly RequestHistoryFactory $requestHistoryFactory,
        private readonly RequestHistoryResource $requestHistoryResource,
        private readonly RequestHistoryCollectionFactory $requestHistoryCollectionFactory,
        private readonly Json $json
    ) {
    }

    public function getLatestForSession(int $sessionId): ?CustomizationRequest
    {
        $collection = $this->requestCollectionFactory->create();
        $collection->addFieldToFilter('session_id', $sessionId);
        $collection->setOrder('request_id', 'DESC');
        $collection->setPageSize(1);
        $request = $collection->getFirstItem();

        return $request->getId() ? $request : null;
    }

    public function getByRestoreTokenHash(string $tokenHash): ?CustomizationRequest
    {
        $collection = $this->requestCollectionFactory->create();
        $collection->addFieldToFilter('restore_token_hash', $tokenHash);
        $collection->setPageSize(1);
        $request = $collection->getFirstItem();

        return $request->getId() ? $request : null;
    }

    public function create(ChatSession $session, array $payload, ?int $customerId, string $customerEmail): CustomizationRequest
    {
        $request = $this->requestFactory->create();
        $request->setData([
            'request_uid' => bin2hex(random_bytes(16)),
            'session_id' => (int)$session->getId(),
            'store_id' => (int)$session->getData('store_id'),
        ]);

        $request->addData([
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'base_product_id' => $payload['base_product_id'],
            'base_product_sku' => $payload['base_product_sku'],
            'title' => $payload['title'],
            'summary' => $payload['summary'],
            'requested_changes_json' => $this->json->serialize($payload['requested_changes']),
            'status' => CustomizationRequest::STATUS_PENDING_REVIEW,
        ]);
        $this->requestResource->save($request);
        $this->addHistory($request, 'agent', null, 'submitted', $request->getData('summary'));

        return $request;
    }

    public function save(CustomizationRequest $request): void
    {
        $this->requestResource->save($request);
    }

    public function addHistory(CustomizationRequest $request, string $actorType, ?int $actorId, string $action, ?string $note = null): void
    {
        $history = $this->requestHistoryFactory->create();
        $history->setData([
            'request_id' => (int)$request->getId(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
        ]);
        $this->requestHistoryResource->save($history);
    }

    /** @return RequestHistory[] */
    public function getHistory(CustomizationRequest $request): array
    {
        $collection = $this->requestHistoryCollectionFactory->create();
        $collection->addFieldToFilter('request_id', (int)$request->getId());
        $collection->setOrder('history_id', 'ASC');

        return $collection->getItems();
    }

    public function createRestoreToken(CustomizationRequest $request): string
    {
        $token = bin2hex(random_bytes(32));
        $request->addData([
            'restore_token_hash' => hash('sha256', $token),
            'restore_expires_at' => gmdate('Y-m-d H:i:s', time() + 7 * 86400),
            'restore_consumed_at' => null,
        ]);
        $this->requestResource->save($request);

        return $token;
    }
}
