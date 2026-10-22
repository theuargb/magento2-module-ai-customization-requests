<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl;

use Perspective\AiCustomizationRequests\Model\Chat\ChatMessage;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;

/**
 * The only place that shapes AiChatSession / AiChatMessage output arrays (see etc/schema.graphqls).
 */
class ChatOutputFormatter
{
    /**
     * @param ChatMessage[] $messages Visible messages.
     * @return array<string, mixed>
     */
    public function session(
        ChatSession $session,
        array $messages,
        ?CustomizationRequest $request,
        bool $emailRequired,
        ?string $resumeToken = null
    ): array {
        return [
            'session_uid' => $session->getUid(),
            'resume_token' => $resumeToken,
            'request_uid' => $request?->getUid(),
            'email_required' => $emailRequired,
            'messages' => array_map(fn(ChatMessage $message): array => $this->message($message), $messages),
        ];
    }

    /** @return array<string, mixed> */
    public function message(ChatMessage $message): array
    {
        return [
            'role' => $message->getRole(),
            'content' => $message->getContent(),
            'created_at' => $message->getCreatedAt(),
            'product_mentions' => array_map(
                static fn(ProductMention $mention): array => [
                    'sku' => $mention->sku,
                    'selected' => $mention->selected,
                ],
                $message->getProductMentions()
            ),
        ];
    }
}
