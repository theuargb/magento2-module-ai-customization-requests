<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\UrlInterface;

/**
 * Admin bell (inbox) notifications linking to the request page.
 */
class AdminNotifier
{
    public function __construct(
        private readonly InboxFactory $inboxFactory,
        private readonly UrlInterface $backendUrl
    ) {
    }

    public function notifySubmitted(CustomizationRequest $request): void
    {
        $this->inboxFactory->create()->addMajor(
            (string)__('New AI customization request'),
            (string)__(
                'Request %1 from %2 is waiting for review.',
                $request->getUid(),
                $request->getCustomerEmail()
            ),
            $this->editUrl($request)
        );
    }

    public function notifyUpdated(CustomizationRequest $request): void
    {
        $this->inboxFactory->create()->addMajor(
            (string)__('AI customization request updated'),
            (string)__(
                'Request %1 from %2 was updated by the customer and is still pending review.',
                $request->getUid(),
                $request->getCustomerEmail()
            ),
            $this->editUrl($request)
        );
    }

    private function editUrl(CustomizationRequest $request): string
    {
        return $this->backendUrl->getUrl(
            'perspective_ai_request/request/edit',
            ['request_id' => $request->getRequestId()]
        );
    }
}
