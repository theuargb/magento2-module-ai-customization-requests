<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Math\Random;
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
        private readonly Random $random,
        private readonly Json $json
    ) {
    }

    public function getByUid(string $requestUid): ?CustomizationRequest
    {
        $collection = $this->requestCollectionFactory->create();
        $collection->addFieldToFilter('request_uid', $requestUid);
        $collection->setPageSize(1);

        $request = $collection->getFirstItem();
        return $request->getId() ? $request : null;
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

    public function getByRestoreToken(string $token): ?CustomizationRequest
    {
        $collection = $this->requestCollectionFactory->create();
        $collection->addFieldToFilter('quote_restore_token', $token);
        $collection->setPageSize(1);

        $request = $collection->getFirstItem();
        return $request->getId() ? $request : null;
    }

    public function createOrUpdateDraft(
        ChatSession $session,
        array $payload,
        ?int $customerId,
        string $customerEmail
    ): CustomizationRequest {
        $request = $this->getLatestForSession((int)$session->getId()) ?? $this->requestFactory->create();
        $isNew = !$request->getId();

        if ($isNew) {
            $request->setData('request_uid', bin2hex(random_bytes(16)));
            $request->setData('session_id', (int)$session->getId());
            $request->setData('store_id', (int)$session->getData('store_id'));
            $request->setData('created_by_agent', 1);
        }

        $request->addData([
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'base_product_id' => $payload['base_product_id'] ?? null,
            'base_product_sku' => $payload['base_product_sku'] ?? null,
            'title' => $payload['title'] ?? (string)__('Customization request'),
            'summary' => $payload['summary'] ?? null,
            'requested_changes_json' => isset($payload['requested_changes'])
                ? $this->json->serialize($payload['requested_changes'])
                : $request->getData('requested_changes_json'),
            'source_transcript_json' => isset($payload['source_transcript'])
                ? $this->json->serialize($payload['source_transcript'])
                : $request->getData('source_transcript_json'),
            'status' => $payload['status'] ?? ($isNew ? CustomizationRequest::STATUS_NEW : $request->getData('status')),
        ]);
        $this->requestResource->save($request);

        $this->addHistory(
            $request,
            'agent',
            0,
            $isNew ? 'request_created' : 'request_updated',
            $payload['summary'] ?? null,
            $payload
        );

        return $request;
    }

    public function save(CustomizationRequest $request): void
    {
        $this->requestResource->save($request);
    }

    public function addHistory(
        CustomizationRequest $request,
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $note = null,
        ?array $payload = null
    ): void {
        /** @var RequestHistory $history */
        $history = $this->requestHistoryFactory->create();
        $history->setData([
            'request_id' => (int)$request->getId(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
            'payload_json' => $payload ? $this->json->serialize($payload) : null,
        ]);
        $this->requestHistoryResource->save($history);
    }

    /**
     * @return RequestHistory[]
     */
    public function getHistory(CustomizationRequest $request): array
    {
        $collection = $this->requestHistoryCollectionFactory->create();
        $collection->addFieldToFilter('request_id', (int)$request->getId());
        $collection->setOrder('history_id', 'ASC');

        return $collection->getItems();
    }

    public function ensureRestoreToken(CustomizationRequest $request): string
    {
        if ($request->getData('quote_restore_token')) {
            return (string)$request->getData('quote_restore_token');
        }

        $request->setData('quote_restore_token', $this->random->getRandomString(40));
        $this->requestResource->save($request);

        return (string)$request->getData('quote_restore_token');
    }
}
