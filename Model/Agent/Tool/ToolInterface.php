<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent\Tool;

use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getParameters(): array;

    public function execute(array $arguments, AgentContext $context): array;
}
