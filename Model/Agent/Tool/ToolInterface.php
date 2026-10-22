<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\ToolResult;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;

/** A function the agent may call. Register implementations in etc/di.xml (ToolRegistry `tools`). */
interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /** @return array<string, mixed> JSON schema of the arguments object. */
    public function getParameters(): array;

    /**
     * @param array<mixed> $arguments Decoded model arguments; validate every field.
     * @throws LocalizedException Message is returned to the model as the tool error.
     */
    public function execute(array $arguments, ChatSession $session): ToolResult;
}
