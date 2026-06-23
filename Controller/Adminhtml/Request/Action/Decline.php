<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestFactory;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Email\CustomerNotifier;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as RequestResource;

class Decline extends Action
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::decline';

    public function __construct(
        Action\Context $context,
        private readonly CustomizationRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly CustomerNotifier $customerNotifier
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $request = $this->loadRequest();
            $reason = trim((string)$this->getRequest()->getParam('decline_reason'));
            if ($reason === '') {
                throw new LocalizedException(__('Decline reason is required.'));
            }

            $request->addData([
                'status' => CustomizationRequest::STATUS_DECLINED,
                'decline_reason' => $reason,
            ]);
            $this->requestResource->save($request);
            $this->customizationRequestService->addHistory(
                $request,
                'admin',
                (int)$this->_auth->getUser()->getId(),
                'declined',
                $reason
            );
            $this->customerNotifier->sendDeclined($request);
            $this->messageManager->addSuccessMessage(__('The request has been declined.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect->setPath('perspective_ai_request/request/edit', ['request_id' => (int)$this->getRequest()->getParam('request_id')]);
    }

    private function loadRequest()
    {
        $request = $this->requestFactory->create();
        $this->requestResource->load($request, (int)$this->getRequest()->getParam('request_id'));
        if (!$request->getId()) {
            throw new LocalizedException(__('The request no longer exists.'));
        }

        return $request;
    }
}
