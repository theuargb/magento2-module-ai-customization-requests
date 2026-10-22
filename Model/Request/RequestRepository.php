<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as RequestResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest\CollectionFactory;

class RequestRepository
{
    public function __construct(
        private readonly CustomizationRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function create(): CustomizationRequest
    {
        return $this->requestFactory->create();
    }

    public function save(CustomizationRequest $request): void
    {
        $this->requestResource->save($request);
    }

    /** @throws NoSuchEntityException */
    public function getById(int $requestId): CustomizationRequest
    {
        $request = $this->requestFactory->create();
        $this->requestResource->load($request, $requestId);
        if (!$request->getId()) {
            throw new NoSuchEntityException(__('The customization request no longer exists.'));
        }

        return $request;
    }

    public function findByRestoreTokenHash(string $tokenHash): ?CustomizationRequest
    {
        $request = $this->requestFactory->create();
        $this->requestResource->load($request, $tokenHash, 'restore_token_hash');

        return $request->getId() ? $request : null;
    }

    public function findLatestForSession(int $sessionId, ?RequestStatus $status = null): ?CustomizationRequest
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('session_id', $sessionId);
        if ($status !== null) {
            $collection->addFieldToFilter('status', $status->value);
        }
        $collection->setOrder('request_id', 'DESC');
        $collection->setPageSize(1);
        /** @var CustomizationRequest $request */
        $request = $collection->getFirstItem();

        return $request->getId() ? $request : null;
    }

    /**
     * Compare-and-set status change: fails when another process already moved the request.
     *
     * @throws LocalizedException
     */
    public function transition(CustomizationRequest $request, RequestStatus $target): void
    {
        $current = $request->getStatus();
        if (!$current->canTransitionTo($target)) {
            throw new LocalizedException(__(
                'A request in status "%1" cannot become "%2".',
                $current->label(),
                $target->label()
            ));
        }

        $affected = $this->requestResource->getConnection()->update(
            $this->requestResource->getMainTable(),
            ['status' => $target->value],
            ['request_id = ?' => $request->getRequestId(), 'status = ?' => $current->value]
        );
        if ($affected !== 1) {
            throw new LocalizedException(__('The request was changed by another action. Reload the page and try again.'));
        }
        $request->applyStatus($target);
    }
}
