<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Controller\Adminhtml\Request\Action;

use Magento\Backend\App\Action\Context;
use Perspective\AiCustomizationRequests\Model\Request\ApprovalService;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;
use Psr\Log\LoggerInterface;

class Notify extends AbstractRequestAction
{
    public const ADMIN_RESOURCE = 'Perspective_AiCustomizationRequests::notify';

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
        $sent = $this->approvalService->notifyCustomer($request, $adminUserId);

        $this->messageManager->addSuccessMessage(
            $sent
                ? __('The customer email was sent.')
                : __('The customer email was not sent; use Resend email.')
        );
    }
}
