<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestFactory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as RequestResource;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::requests';

    public function __construct(
        Action\Context $context,
        private readonly CustomizationRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $requestId = (int)$this->getRequest()->getParam('request_id');
        $requestModel = $this->requestFactory->create();
        $this->requestResource->load($requestModel, $requestId);

        if (!$requestModel->getId()) {
            throw new LocalizedException(__('The request no longer exists.'));
        }

        $this->registry->register('perspective_ai_current_request', $requestModel);

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Perspective_AiCustomizationRequests::requests');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Request %1', $requestModel->getData('request_uid')));

        return $resultPage;
    }
}
