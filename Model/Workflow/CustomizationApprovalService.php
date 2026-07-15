<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Email\CustomerNotifier;

class CustomizationApprovalService
{
    public function __construct(
        private readonly CustomizationRequestService $requestService,
        private readonly CustomizationProductCreator $productCreator,
        private readonly PreparedQuoteCreator $quoteCreator,
        private readonly CustomerNotifier $customerNotifier
    ) {
    }

    public function approve(CustomizationRequest $request, int $adminUserId, ?string $note): void
    {
        if ((string)$request->getData('status') !== CustomizationRequest::STATUS_PENDING_REVIEW) {
            throw new LocalizedException(__('Only pending requests can be approved.'));
        }

        $request->setData('status', CustomizationRequest::STATUS_PROCESSING);
        $this->requestService->save($request);
        try {
            $productId = $this->productCreator->create($request);
            $request->addData([
                'custom_product_id' => $productId,
                'custom_product_sku' => sprintf('%s-CUSTOM-%s', (string)$request->getData('base_product_sku'), $request->getId()),
            ]);
            $this->requestService->save($request);
            $quoteId = $this->quoteCreator->create($request, $productId);
            $request->addData([
                'prepared_quote_id' => $quoteId,
                'status' => CustomizationRequest::STATUS_APPROVED,
                'approved_by_user_id' => $adminUserId,
                'approval_notes' => $note,
            ]);
            $this->requestService->save($request);
            $token = $this->requestService->createRestoreToken($request);
            $this->customerNotifier->sendApproved($request, $token);
            $request->setData('approval_email_sent_at', gmdate('Y-m-d H:i:s'));
            $this->requestService->save($request);
            $this->requestService->addHistory($request, 'admin', $adminUserId, 'approved', $note);
        } catch (\Throwable $exception) {
            $request->setData('status', CustomizationRequest::STATUS_PENDING_REVIEW);
            $this->requestService->save($request);
            throw $exception;
        }
    }

    public function decline(CustomizationRequest $request, int $adminUserId, string $reason): void
    {
        if ((string)$request->getData('status') !== CustomizationRequest::STATUS_PENDING_REVIEW) {
            throw new LocalizedException(__('Only pending requests can be declined.'));
        }
        $request->addData(['status' => CustomizationRequest::STATUS_DECLINED, 'decline_reason' => $reason]);
        $this->requestService->save($request);
        $this->customerNotifier->sendDeclined($request);
        $request->setData('decline_email_sent_at', gmdate('Y-m-d H:i:s'));
        $this->requestService->save($request);
        $this->requestService->addHistory($request, 'admin', $adminUserId, 'declined', $reason);
    }
}
