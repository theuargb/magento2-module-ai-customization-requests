<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Request\ApprovalService;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;
use Psr\Log\LoggerInterface;

class Decline extends AbstractRequestAction
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::decline';

    public function __construct(
        Context $context,
        RequestRepository $requestRepository,
        LoggerInterface $logger,
        private readonly ApprovalService $approvalService
    ) {
        parent::__construct($context, $requestRepository, $logger);
    }

    protected function handle(CustomizationRequest $request, int $adminUserId): void
    {
        $reason = trim((string)$this->getRequest()->getParam('decision_note'));
        if ($reason === '') {
            throw new LocalizedException(__('A decline reason is required.'));
        }

        $sent = $this->approvalService->decline($request, $adminUserId, $reason);

        $this->messageManager->addSuccessMessage(
            $sent
                ? __('The request has been declined and the customer email was sent.')
                : __('Declined. The customer email was not sent; use Resend email.')
        );
    }
}
