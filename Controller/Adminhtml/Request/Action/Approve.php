<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestFactory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as RequestResource;
use Perspective\AiCustomizationRequests\Model\Workflow\CustomizationApprovalService;

class Approve extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::approve';

    public function __construct(
        Action\Context $context,
        private readonly CustomizationRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly CustomizationApprovalService $approvalService
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $request = $this->loadRequest();
            $note = trim((string)$this->getRequest()->getParam('decision_note'));
            $this->approvalService->approve($request, (int)$this->_auth->getUser()->getId(), $note ?: null);
            $this->messageManager->addSuccessMessage(__('The request has been approved and emailed.'));
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
