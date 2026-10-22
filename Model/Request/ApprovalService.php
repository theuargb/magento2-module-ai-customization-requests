<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Perspective\AiCustomizationRequests\Model\Config\EmailConfig;
use Perspective\AiCustomizationRequests\Model\Infra\TokenService;

/**
 * Admin decisions.
 * approve: transition pending->processing (claims the request), create custom product, set product +
 *   restore token + decision, transition processing->approved, history; on failure before approval:
 *   transition back to pending and rethrow. Then email (outside the claim): failures are logged and
 *   reported via the return value, never revert the approval.
 * decline: transition pending->declined with reason, history, then email the same way.
 * notifyCustomer: (re)sends the decision email for approved/declined requests; approved gets a fresh token.
 */
class ApprovalService
{
    public function __construct(
        private readonly RequestRepository $requestRepository,
        private readonly RequestHistoryRepository $historyRepository,
        private readonly CustomProductCreator $productCreator,
        private readonly CustomerNotifier $customerNotifier,
        private readonly TokenService $tokenService,
        private readonly EmailConfig $emailConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return bool Whether the customer email was sent. */
    public function approve(CustomizationRequest $request, int $adminUserId, ?string $note): bool
    {
        $token = $this->tokenService->secret();
        $claimed = false;
        try {
            $this->requestRepository->transition($request, RequestStatus::Processing);
            $claimed = true;

            $customProduct = $this->productCreator->create($request);
            $request->setCustomProduct((int)$customProduct->getId(), (string)$customProduct->getSku());
            $request->setRestoreToken(
                $this->tokenService->hash($token),
                $this->emailConfig->getRestoreLinkTtlDays($request->getStoreId())
            );
            $request->setDecision($note, $adminUserId);
            $this->requestRepository->save($request);

            $this->requestRepository->transition($request, RequestStatus::Approved);
            $this->historyRepository->add(
                $request,
                RequestHistory::ACTOR_ADMIN,
                $adminUserId,
                'approved',
                $note
            );
        } catch (\Throwable $exception) {
            if ($claimed) {
                try {
                    $this->requestRepository->transition($request, RequestStatus::PendingReview);
                } catch (\Throwable $rollbackException) {
                    $this->logger->error(
                        'Failed to roll back customization request {request_id} to pending_review: {error}',
                        [
                            'request_id' => $request->getRequestId(),
                            'error' => $rollbackException->getMessage(),
                        ]
                    );
                }
            }

            throw $exception;
        }

        return $this->sendAndTrack(
            fn (): bool => $this->customerNotifier->sendApproved($request, $token),
            $request,
            $adminUserId
        );
    }

    /** @return bool Whether the customer email was sent. */
    public function decline(CustomizationRequest $request, int $adminUserId, string $reason): bool
    {
        $this->requestRepository->transition($request, RequestStatus::Declined);
        $request->setDecision($reason, $adminUserId);
        $this->requestRepository->save($request);
        $this->historyRepository->add(
            $request,
            RequestHistory::ACTOR_ADMIN,
            $adminUserId,
            'declined',
            $reason
        );

        return $this->sendAndTrack(
            fn (): bool => $this->customerNotifier->sendDeclined($request),
            $request,
            $adminUserId
        );
    }

    /**
     * @return bool Whether the customer email was sent.
     * @throws LocalizedException Request has no decision yet.
     */
    public function notifyCustomer(CustomizationRequest $request, int $adminUserId): bool
    {
        $status = $request->getStatus();
        if ($status !== RequestStatus::Approved && $status !== RequestStatus::Declined) {
            throw new LocalizedException(__('The request has no decision to notify yet.'));
        }

        if ($status === RequestStatus::Approved) {
            $token = $this->tokenService->secret();
            $request->setRestoreToken(
                $this->tokenService->hash($token),
                $this->emailConfig->getRestoreLinkTtlDays($request->getStoreId())
            );
            $this->requestRepository->save($request);
            $this->historyRepository->add($request, RequestHistory::ACTOR_ADMIN, $adminUserId, 'notified');

            return $this->sendAndTrack(
                fn (): bool => $this->customerNotifier->sendApproved($request, $token),
                $request,
                $adminUserId
            );
        }

        $this->historyRepository->add($request, RequestHistory::ACTOR_ADMIN, $adminUserId, 'notified');

        return $this->sendAndTrack(
            fn (): bool => $this->customerNotifier->sendDeclined($request),
            $request,
            $adminUserId
        );
    }

    /**
     * Email outside the decided state: success marks notified_at, failure is logged
     * and reported as false without reverting anything.
     *
     * @param callable(): bool $send
     */
    private function sendAndTrack(callable $send, CustomizationRequest $request, int $adminUserId): bool
    {
        try {
            $sent = $send();
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Customization request {request_id} decision email failed: {error}',
                [
                    'request_id' => $request->getRequestId(),
                    'error' => $exception->getMessage(),
                ]
            );

            return false;
        }

        if ($sent) {
            $request->markNotified();
            $this->requestRepository->save($request);
        }

        return $sent;
    }
}
