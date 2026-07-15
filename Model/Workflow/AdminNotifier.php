<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Workflow;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\UrlInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class AdminNotifier
{
    public function __construct(
        private readonly InboxFactory $inboxFactory,
        private readonly UrlInterface $backendUrl
    ) {
    }

    public function notifyNewRequest(CustomizationRequest $request): void
    {
        $notification = $this->inboxFactory->create();
        $notification->addMajor(
            __('New AI customization request'),
            __(
                'Request %1 for %2 is waiting for review.',
                $request->getData('request_uid'),
                $request->getData('customer_email')
            ),
            $this->backendUrl->getUrl('perspective_ai_request/request/edit', ['request_id' => (int)$request->getId()])
        );
    }
}
