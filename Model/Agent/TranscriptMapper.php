<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Perspective\AiCustomizationRequests\Model\Chat\ChatMessage;

/**
 * Persisted transcript -> OpenAI messages, including assistant tool_calls and tool results.
 */
class TranscriptMapper
{
    public function __construct(private readonly PromptBuilder $promptBuilder)
    {
    }

    /**
     * @param ChatMessage[] $transcript
     * @return list<array<string, mixed>>
     */
    public function map(array $transcript): array
    {
        $messages = [];
        foreach ($transcript as $message) {
            $messages[] = match ($message->getRole()) {
                ChatMessage::ROLE_USER => $this->userMessage($message),
                ChatMessage::ROLE_ASSISTANT => $this->assistantMessage($message),
                ChatMessage::ROLE_TOOL => $this->toolMessage($message),
                default => null,
            };
        }

        return array_values(array_filter($messages, static fn(?array $m): bool => $m !== null));
    }

    /** @return array<string, mixed> */
    private function userMessage(ChatMessage $message): array
    {
        return [
            'role' => ChatMessage::ROLE_USER,
            'content' => $this->promptBuilder->userTurn(
                $message->getContent(),
                $message->getProductMentions(),
                $message->getPageContext()
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function assistantMessage(ChatMessage $message): array
    {
        $entry = [
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $message->getContent(),
        ];
        $toolCalls = $message->getToolCalls();
        if ($toolCalls !== []) {
            $entry['tool_calls'] = $toolCalls;
        }

        return $entry;
    }

    /** @return array<string, mixed> */
    private function toolMessage(ChatMessage $message): array
    {
        return [
            'role' => ChatMessage::ROLE_TOOL,
            'tool_call_id' => $message->getToolCallId() ?? '',
            'content' => $message->getContent(),
        ];
    }
}
