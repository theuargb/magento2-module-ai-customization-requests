<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Magento\Framework\Model\AbstractModel;
use Perspective\AiCustomizationRequests\Model\Infra\JsonCodec;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage as ChatMessageResource;

/**
 * One transcript entry. Roles: `user` (shopper), `assistant` (model reply or tool-call request),
 * `tool` (tool result answering `tool_call_id`). Only user messages and final assistant replies are visible.
 */
class ChatMessage extends AbstractModel
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    protected function _construct(): void
    {
        $this->_init(ChatMessageResource::class);
    }

    public function getMessageId(): int
    {
        return (int)$this->getId();
    }

    public function getSessionId(): int
    {
        return (int)$this->getData('session_id');
    }

    public function setSessionId(int $sessionId): void
    {
        $this->setData('session_id', $sessionId);
    }

    public function getRole(): string
    {
        return (string)$this->getData('role');
    }

    public function setRole(string $role): void
    {
        $this->setData('role', $role);
    }

    public function getContent(): string
    {
        return (string)$this->getData('content');
    }

    public function setContent(string $content): void
    {
        $this->setData('content', $content);
    }

    public function getClientMessageId(): ?string
    {
        $id = (string)$this->getData('client_message_id');

        return $id === '' ? null : $id;
    }

    public function setClientMessageId(?string $clientMessageId): void
    {
        $this->setData('client_message_id', $clientMessageId);
    }

    /** @return list<array<string, mixed>> OpenAI-format tool calls requested by the assistant. */
    public function getToolCalls(): array
    {
        return array_values(array_filter(JsonCodec::decodeArray($this->getData('tool_calls_json')), 'is_array'));
    }

    /** @param list<array<string, mixed>> $toolCalls */
    public function setToolCalls(array $toolCalls): void
    {
        $this->setData('tool_calls_json', $toolCalls === [] ? null : JsonCodec::encode($toolCalls));
    }

    public function getToolCallId(): ?string
    {
        $id = (string)$this->getData('tool_call_id');

        return $id === '' ? null : $id;
    }

    public function setToolCallId(?string $toolCallId): void
    {
        $this->setData('tool_call_id', $toolCallId);
    }

    /** @return ProductMention[] */
    public function getProductMentions(): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $item): ?ProductMention => is_array($item) ? ProductMention::fromArray($item) : null,
            JsonCodec::decodeArray($this->getData('product_mentions_json'))
        )));
    }

    /** @param ProductMention[] $mentions */
    public function setProductMentions(array $mentions): void
    {
        $this->setData('product_mentions_json', $mentions === []
            ? null
            : JsonCodec::encode(array_map(static fn(ProductMention $m): array => $m->toArray(), $mentions)));
    }

    public function getPageContext(): ?PageContext
    {
        return PageContext::fromArray(JsonCodec::decodeArray($this->getData('page_context_json')));
    }

    public function setPageContext(?PageContext $context): void
    {
        $this->setData('page_context_json', $context === null ? null : JsonCodec::encode($context->toArray()));
    }

    public function getModelName(): ?string
    {
        $name = (string)$this->getData('model_name');

        return $name === '' ? null : $name;
    }

    public function setModelName(?string $modelName): void
    {
        $this->setData('model_name', $modelName);
    }

    public function getCreatedAt(): string
    {
        return (string)$this->getData('created_at');
    }

    public function isFinalReply(): bool
    {
        return $this->getRole() === self::ROLE_ASSISTANT && $this->getToolCalls() === [];
    }

    public function isVisible(): bool
    {
        return $this->getRole() === self::ROLE_USER || $this->isFinalReply();
    }
}
