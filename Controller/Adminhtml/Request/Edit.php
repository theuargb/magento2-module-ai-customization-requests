<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\Page;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::requests';

    public function __construct(
        Context $context,
        private readonly RequestRepository $requestRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $request = $this->requestRepository->getById(
                (int)$this->getRequest()->getParam('request_id')
            );
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('The request no longer exists.'));

            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath('perspective_ai_request/request/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Perspective_AiCustomizationRequests::requests');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Request %1', $request->getUid()));

        return $resultPage;
    }
}
