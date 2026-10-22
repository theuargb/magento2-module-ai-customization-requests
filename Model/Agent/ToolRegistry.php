<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\Tool\ToolInterface;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;

/**
 * Registered agent tools (etc/di.xml `tools`), keyed by tool name.
 */
class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param ToolInterface[] $tools */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }
    }

    /** @return list<array<string, mixed>> OpenAI function tool definitions. */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters(),
                ],
            ];
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws LocalizedException Unknown tool or tool failure.
     */
    public function execute(string $name, array $arguments, ChatSession $session): ToolResult
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            throw new LocalizedException(__('Tool "%1" is not available.', $name));
        }

        return $tool->execute($arguments, $session);
    }
}
