<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Restore;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
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

        /** @var Page $page */
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Restoring your customization'));

        return $page;
    }
}
