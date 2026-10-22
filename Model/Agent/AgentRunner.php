<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Perspective\AiCustomizationRequests\Model\Chat\ChatMessage;
use Perspective\AiCustomizationRequests\Model\Chat\ChatMessageRepository;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Config\OpenRouterConfig;
use Perspective\AiCustomizationRequests\Model\Infra\ExternalServiceException;
use Perspective\AiCustomizationRequests\Model\Infra\JsonCodec;
use Perspective\AiCustomizationRequests\Model\OpenRouter\OpenRouterClient;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;

/**
 * Tool loop for the session's transcript (which already ends with the new user message).
 * Every assistant tool-call message and tool result is persisted as it happens; a tool failure becomes
 * a `{"error": "..."}` tool result for the model. The final reply carries the product mentions
 * collected from tool results of this run.
 */
class AgentRunner
{
    public function __construct(
        private readonly OpenRouterClient $openRouterClient,
        private readonly OpenRouterConfig $openRouterConfig,
        private readonly AgentConfig $agentConfig,
        private readonly ToolRegistry $toolRegistry,
        private readonly PromptBuilder $promptBuilder,
        private readonly TranscriptMapper $transcriptMapper,
        private readonly ChatMessageRepository $messageRepository,
        private readonly RequestRepository $requestRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @throws ExternalServiceException OpenRouter unavailable. */
    public function run(ChatSession $session): AgentResult
    {
        $storeId = $session->getStoreId();
        $model = $this->openRouterConfig->getChatModel($storeId);

        /** @var list<array<string, mixed>> $messages */
        $messages = array_merge(
            [[
                'role' => 'system',
                'content' => $this->promptBuilder->system(
                    $session,
                    $this->requestRepository->findLatestForSession($session->getSessionId())
                ),
            ]],
            $this->transcriptMapper->map($this->messageRepository->getTranscript($session->getSessionId()))
        );
        $tools = $this->toolRegistry->getDefinitions();

        /** @var array<string, ProductMention> $mentions Collected during this run, deduped by SKU, order kept. */
        $mentions = [];
        $latestRequest = null;
        $replyContent = null;

        for ($iteration = 0; $iteration < $this->agentConfig->getMaxIterations($storeId); $iteration++) {
            $choice = $this->openRouterClient->chat($messages, $tools, $model, $storeId);
            $toolCalls = array_values(array_filter(
                is_array($choice['tool_calls'] ?? null) ? $choice['tool_calls'] : [],
                'is_array'
            ));

            if ($toolCalls === []) {
                $replyContent = trim((string)($choice['content'] ?? ''));
                if ($replyContent === '') {
                    $replyContent = $this->promptBuilder->emptyReplyFallback();
                }
                break;
            }

            $assistant = $this->messageRepository->create($session, ChatMessage::ROLE_ASSISTANT);
            $assistant->setContent((string)($choice['content'] ?? ''));
            $assistant->setToolCalls($toolCalls);
            $assistant->setModelName($model);
            $this->messageRepository->save($assistant);
            $messages[] = [
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => (string)($choice['content'] ?? ''),
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $index => $toolCall) {
                $callId = (string)($toolCall['id'] ?? '');
                if ($callId === '') {
                    $callId = 'call_' . $index;
                }
                $toolName = (string)($toolCall['function']['name'] ?? '');
                $arguments = JsonCodec::decodeArray(
                    is_string($toolCall['function']['arguments'] ?? null) ? $toolCall['function']['arguments'] : null
                );

                try {
                    $result = $this->toolRegistry->execute($toolName, $arguments, $session);
                    $payload = $result->payload;
                    foreach ($result->mentions as $mention) {
                        $mentions[$mention->sku] ??= $mention;
                    }
                    if ($result->request !== null) {
                        $latestRequest = $result->request;
                    }
                } catch (LocalizedException | NoSuchEntityException $e) {
                    $payload = ['error' => (string)$e->getMessage()];
                } catch (\Throwable $e) {
                    $this->logger->error('AI chat tool failure: ' . $e->getMessage(), ['tool' => $toolName]);
                    $payload = ['error' => (string)__('The tool could not complete the request.')];
                }

                $content = JsonCodec::encode($payload);
                $toolMessage = $this->messageRepository->create($session, ChatMessage::ROLE_TOOL);
                $toolMessage->setToolCallId($callId);
                $toolMessage->setContent($content);
                $this->messageRepository->save($toolMessage);
                $messages[] = [
                    'role' => ChatMessage::ROLE_TOOL,
                    'tool_call_id' => $callId,
                    'content' => $content,
                ];
            }
        }

        if ($replyContent === null) {
            $replyContent = $this->promptBuilder->iterationLimitFallback();
        }

        $reply = $this->messageRepository->create($session, ChatMessage::ROLE_ASSISTANT);
        $reply->setContent($replyContent);
        $reply->setProductMentions(array_values($mentions));
        $reply->setModelName($model);
        $this->messageRepository->save($reply);

        return new AgentResult($reply, $latestRequest);
    }
}
