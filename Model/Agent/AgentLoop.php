<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Magento\Framework\Serialize\Serializer\Json;
use Perspective\AiCustomizationRequests\Model\ChatSession;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\OpenRouter\Client as OpenRouterClient;

class AgentLoop
{
    public function __construct(
        private readonly OpenRouterClient $openRouterClient,
        private readonly ModuleConfig $moduleConfig,
        private readonly ToolExecutor $toolExecutor,
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService,
        private readonly Json $json
    ) {
    }

    public function run(ChatSession $session, ?int $customerId = null): array
    {
        $activeRequest = $this->customizationRequestService->getLatestForSession((int)$session->getId());
        $context = new AgentContext(
            $session,
            $customerId,
            $session->getData('customer_email') ? (string)$session->getData('customer_email') : null,
            $activeRequest
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        foreach ($this->chatSessionService->getMessages($session) as $message) {
            $messages[] = [
                'role' => (string)$message->getData('role'),
                'content' => (string)$message->getData('content'),
            ];
        }

        $productMentions = [];
        $toolDefinitions = $this->toolExecutor->getToolDefinitions();
        $assistantMessage = '';
        $modelName = $this->moduleConfig->getChatModel((int)$session->getData('store_id'));

        for ($iteration = 0; $iteration < $this->moduleConfig->getMaxIterations((int)$session->getData('store_id')); $iteration++) {
            $response = $this->openRouterClient->chatCompletion(
                $messages,
                $toolDefinitions,
                $modelName,
                (int)$session->getData('store_id')
            );
            $choiceMessage = $response['choices'][0]['message'] ?? [];
            $toolCalls = $choiceMessage['tool_calls'] ?? [];

            if ($toolCalls && is_array($toolCalls)) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $choiceMessage['content'] ?? '',
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall['function']['name'] ?? '';
                    $rawArguments = $toolCall['function']['arguments'] ?? '{}';
                    $arguments = $this->decodeArguments($rawArguments);
                    $result = $this->toolExecutor->execute($toolName, $arguments, $context);

                    if ($toolName === 'search_catalog' && !empty($result['products'])) {
                        $productMentions = $result['products'];
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? $toolName,
                        'name' => $toolName,
                        'content' => $this->json->serialize($result),
                    ];
                }

                continue;
            }

            $assistantMessage = trim((string)($choiceMessage['content'] ?? ''));
            if ($assistantMessage === '') {
                $assistantMessage = (string)__('I need a bit more detail to continue.');
            }
            break;
        }

        if ($assistantMessage === '') {
            $assistantMessage = (string)__('I could not finish that request right now. Please try again.');
        }

        return [
            'content' => $assistantMessage,
            'product_mentions' => $productMentions,
            'request' => $context->getRequest(),
            'model_name' => $modelName,
        ];
    }

    private function buildSystemPrompt(AgentContext $context): string
    {
        $requestUid = $context->getRequest()?->getData('request_uid') ?: 'none';
        $customerEmail = $context->getCustomerEmail() ?: 'missing';

        return implode("\n", [
            'You are the Perspective storefront shopping assistant.',
            'Recommend only products returned by tools. Do not invent catalog data.',
            'When a shopper asks for product customization, collect the base product and requested changes.',
            'For guests, make sure you have an email before creating a customization request.',
            'Use managed tools only for search, product context, email capture, and request submission.',
            'Never promise a price, approval, product creation, or checkout. Submitted requests are reviewed by an administrator.',
            'Current session request_uid: ' . $requestUid,
            'Current known customer_email: ' . $customerEmail,
        ]);
    }

    private function decodeArguments(string $rawArguments): array
    {
        try {
            $decoded = $this->json->unserialize($rawArguments);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

}
