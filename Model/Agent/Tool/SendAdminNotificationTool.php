<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\Workflow\AdminNotifier;

class SendAdminNotificationTool implements ToolInterface
{
    public function __construct(
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly AdminNotifier $adminNotifier
    ) {
    }

    public function getName(): string
    {
        return 'send_admin_notification';
    }

    public function getDescription(): string
    {
        return 'Creates an admin inbox notification for a customization request that needs review.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'request_uid' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $request = $context->getRequest();
        if (!empty($arguments['request_uid'])) {
            $request = $this->customizationRequestService->getByUid((string)$arguments['request_uid']);
        }

        if (!$request || !$request->getId()) {
            throw new LocalizedException(__('Customization request is not available.'));
        }

        $this->adminNotifier->notifyNewRequest($request);
        $this->customizationRequestService->addHistory($request, 'agent', 0, 'admin_notified');

        return [
            'request_uid' => $request->getData('request_uid'),
            'notified' => true,
        ];
    }
}
