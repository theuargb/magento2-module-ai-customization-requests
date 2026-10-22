<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action\Context;
use Perspective\AiCustomizationRequests\Model\Request\ApprovalService;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;
use Psr\Log\LoggerInterface;

class Approve extends AbstractRequestAction
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::approve';

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
        $note = trim((string)$this->getRequest()->getParam('decision_note'));
        $sent = $this->approvalService->approve($request, $adminUserId, $note !== '' ? $note : null);

        $this->messageManager->addSuccessMessage(
            $sent
                ? __('The request has been approved and the customer email was sent.')
                : __('Approved. The customer email was not sent; use Resend email.')
        );
    }
}
