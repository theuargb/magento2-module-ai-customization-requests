<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class SaveCustomerEmailTool implements ToolInterface
{
    public function __construct(
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService
    ) {
    }

    public function getName(): string
    {
        return 'save_customer_email';
    }

    public function getDescription(): string
    {
        return 'Stores the customer email on the chat session for follow-up and request notifications.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string'],
            ],
            'required' => ['email'],
        ];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $email = trim((string)($arguments['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('A valid customer email is required.'));
        }

        $this->chatSessionService->attachCustomerEmail($context->getSession(), $email);
        $context->setCustomerEmail($email);

        if ($context->getRequest()) {
            $context->getRequest()->setData('customer_email', $email);
            $this->customizationRequestService->save($context->getRequest());
        }

        return ['email' => $email];
    }
}
