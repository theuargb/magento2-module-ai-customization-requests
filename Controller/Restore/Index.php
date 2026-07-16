<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Restore;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $token = trim((string)$this->getRequest()->getParam('token'));
        if ($token === '') {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Restoring customization cart'));
        $restoreWidget = $page->getLayout()->getBlock('perspective.ai.restore.widget');
        if (!$restoreWidget) {
            throw new LocalizedException(__('The customization restore page is not configured.'));
        }
        $restoreWidget->setData('restore_token', $token);

        return $page;
    }
}
