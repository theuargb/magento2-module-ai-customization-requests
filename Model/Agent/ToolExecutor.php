<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use Perspective\AiCustomizationRequests\Model\Agent\Tool\ToolInterface;
use Perspective\AiCustomizationRequests\Model\ToolExecutionService;

class ToolExecutor
{
    /**
     * @var ToolInterface[]
     */
    private array $toolsByName = [];

    /**
     * @param ToolExecutionService $toolExecutionService
     * @param ToolInterface[] $tools
     */
    public function __construct(
        private readonly ToolExecutionService $toolExecutionService,
        array $tools = []
    ) {
        foreach ($tools as $tool) {
            $this->toolsByName[$tool->getName()] = $tool;
        }
    }

    public function getToolDefinitions(): array
    {
        $definitions = [];
        foreach ($this->toolsByName as $tool) {
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

    public function execute(string $name, array $arguments, AgentContext $context): array
    {
        $tool = $this->toolsByName[$name] ?? null;
        if (!$tool) {
            throw new LocalizedException(__('Tool "%1" is not available.', $name));
        }

        try {
            $result = $tool->execute($arguments, $context);
            $this->toolExecutionService->log(
                (int)$context->getSession()->getId(),
                $context->getRequest() ? (int)$context->getRequest()->getId() : null,
                $name,
                'success',
                $arguments,
                $result
            );
            return $result;
        } catch (\Throwable $exception) {
            $this->toolExecutionService->log(
                (int)$context->getSession()->getId(),
                $context->getRequest() ? (int)$context->getRequest()->getId() : null,
                $name,
                'failed',
                $arguments,
                null,
                $exception->getMessage()
            );
            throw $exception;
        }
    }
}
