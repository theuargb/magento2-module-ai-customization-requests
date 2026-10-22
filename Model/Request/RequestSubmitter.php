<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\AiCustomizationRequests\Model\Catalog\StorefrontProductGuard;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Infra\TokenService;

/**
 * Agent-facing request writes. One pending request per chat session: a second submission while the
 * previous one is still pending updates it (history "updated") instead of creating a duplicate.
 */
class RequestSubmitter
{
    private const ACTOR_AGENT_ID = 0;

    public function __construct(
        private readonly RequestRepository $requestRepository,
        private readonly RequestHistoryRepository $historyRepository,
        private readonly StorefrontProductGuard $productGuard,
        private readonly AdminNotifier $adminNotifier,
        private readonly TokenService $tokenService
    ) {
    }

    /**
     * @param array<mixed> $requestedChanges Structured change list from the model.
     * @throws LocalizedException Session has no customer email, product unavailable, or empty fields.
     */
    public function submit(
        ChatSession $session,
        string $baseSku,
        string $title,
        string $summary,
        array $requestedChanges
    ): CustomizationRequest {
        $email = $session->getCustomerEmail();
        if ($email === null || $email === '') {
            throw new LocalizedException(
                __('An email address is required before submitting a request.')
            );
        }

        $title = trim($title);
        if ($title === '') {
            throw new LocalizedException(__('A request title is required.'));
        }
        if (mb_strlen($title) > 255) {
            throw new LocalizedException(__('The request title must not exceed 255 characters.'));
        }

        $summary = trim($summary);
        if ($summary === '') {
            throw new LocalizedException(__('A request summary is required.'));
        }
        if (mb_strlen($summary) > 5000) {
            throw new LocalizedException(__('The request summary must not exceed 5000 characters.'));
        }

        try {
            $product = $this->productGuard->getBySku($baseSku, $session->getStoreId());
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(
                __('The referenced product is not available in this store.'),
                $exception
            );
        }

        $pending = $this->requestRepository->findLatestForSession(
            $session->getSessionId(),
            RequestStatus::PendingReview
        );

        if ($pending !== null) {
            $pending->setDetails($title, $summary, $requestedChanges);
            $pending->setBaseProduct((int)$product->getId(), (string)$product->getSku());
            $this->requestRepository->save($pending);
            $this->historyRepository->add(
                $pending,
                RequestHistory::ACTOR_AGENT,
                self::ACTOR_AGENT_ID,
                'updated'
            );
            $this->adminNotifier->notifyUpdated($pending);

            return $pending;
        }

        $request = $this->requestRepository->create();
        $request->initialize(
            $this->tokenService->uid(),
            $session->getSessionId(),
            $session->getStoreId(),
            $session->getCustomerId(),
            $email
        );
        $request->setBaseProduct((int)$product->getId(), (string)$product->getSku());
        $request->setDetails($title, $summary, $requestedChanges);
        $this->requestRepository->save($request);
        $this->historyRepository->add(
            $request,
            RequestHistory::ACTOR_AGENT,
            self::ACTOR_AGENT_ID,
            'submitted'
        );
        $this->adminNotifier->notifySubmitted($request);

        return $request;
    }
}
