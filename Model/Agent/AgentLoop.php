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
            $activeRequest
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        foreach ($this->chatSessionService->getMessages($session) as $message) {
            $role = (string)$message->getData('role');
            $messages[] = [
                'role' => $role,
                'content' => $this->appendSelectedProductContext(
                    $this->appendPageContext(
                        (string)$message->getData('content'),
                        $role,
                        $this->chatSessionService->getPageContext($message)
                    ),
                    $role,
                    $this->chatSessionService->getProductMentions($message)
                ),
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
        return implode("\n", [
            'You are the Perspective storefront shopping assistant.',
            'Recommend only products returned by tools. Do not invent catalog data.',
            'When a shopper asks for product customization, collect the base product and requested changes.',
            'Customer contact details are handled by the storefront and are never part of this conversation.',
            'Use managed tools only for search, product context, and request submission.',
            'Never promise a price, approval, product creation, or checkout. Submitted requests are reviewed by an administrator.',
            'Current session request_uid: ' . $requestUid,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $productMentions
     */
    private function appendSelectedProductContext(string $content, string $role, array $productMentions): string
    {
        if ($role !== 'user') {
            return $content;
        }

        $selectedSkus = array_values(array_filter(array_map(
            static fn(array $mention): string => !empty($mention['selected']) ? trim((string)($mention['sku'] ?? '')) : '',
            $productMentions
        )));
        if ($selectedSkus === []) {
            return $content;
        }

        return $content . "\n\n[The shopper selected catalog product SKU: " . implode(', ', $selectedSkus)
            . '. Use it as the base product for this request. Do not ask for its SKU, name, or link unless the shopper explicitly changes the selection.]';
    }

    /** @param array<string, mixed>|null $pageContext */
    private function appendPageContext(string $content, string $role, ?array $pageContext): string
    {
        if ($role !== 'user' || !$pageContext) {
            return $content;
        }

        $entityType = trim((string)($pageContext['entity_type'] ?? ''));
        $entityId = (int)($pageContext['entity_id'] ?? 0);
        $requestPath = trim((string)($pageContext['request_path'] ?? ''));
        if ($entityType === '' || $entityId <= 0 || $requestPath === '') {
            return $content;
        }

        return $content . "\n\n[The shopper is currently viewing storefront page '" . $requestPath
            . "', resolved as " . $entityType . ' entity ID ' . $entityId
            . $this->formatCurrentProductContext($pageContext)
            . '. Use this page context to understand references such as “this” or “here”.]';
    }

    /** @param array<string, mixed> $pageContext */
    private function formatCurrentProductContext(array $pageContext): string
    {
        $sku = trim((string)($pageContext['product_sku'] ?? ''));
        if ($sku === '') {
            return '';
        }

        $name = trim((string)($pageContext['product_name'] ?? ''));

        return '; product SKU: ' . $sku . ($name === '' ? '' : '; product name: ' . $name)
            . '. When the shopper refers to this product page, use this SKU as the base product unless they selected another product';
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
