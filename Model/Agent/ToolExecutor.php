<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\Tool\ToolInterface;

class ToolExecutor
{
    /** @var ToolInterface[] */
    private array $toolsByName = [];

    /** @param ToolInterface[] $tools */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->toolsByName[$tool->getName()] = $tool;
        }
    }

    public function getToolDefinitions(): array
    {
        return array_values(array_map(static fn(ToolInterface $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ],
        ], $this->toolsByName));
    }

    public function execute(string $name, array $arguments, AgentContext $context): array
    {
        $tool = $this->toolsByName[$name] ?? null;
        if (!$tool) {
            throw new LocalizedException(__('Tool "%1" is not available.', $name));
        }

        return $tool->execute($arguments, $context);
    }
}
