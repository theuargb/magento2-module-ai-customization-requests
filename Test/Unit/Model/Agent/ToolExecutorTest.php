<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Test\Unit\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\Agent\Tool\ToolInterface;
use Perspective\AiCustomizationRequests\Model\Agent\ToolExecutor;
use Perspective\AiCustomizationRequests\Model\ChatSession;
use Perspective\AiCustomizationRequests\Model\ToolExecutionService;

class ToolExecutorTest extends TestCase
{
    private ToolExecutionService&MockObject $toolExecutionService;

    protected function setUp(): void
    {
        $this->toolExecutionService = $this->createMock(ToolExecutionService::class);
    }

    public function testExecuteLogsSuccessfulToolCall(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('search_catalog');
        $tool->method('getDescription')->willReturn('desc');
        $tool->method('getParameters')->willReturn(['type' => 'object']);
        $tool->expects(self::once())
            ->method('execute')
            ->with(['query' => 'boots'])
            ->willReturn(['products' => [['sku' => 'sku-1']]]);

        $this->toolExecutionService->expects(self::once())
            ->method('log')
            ->with(10, null, 'search_catalog', 'success', ['query' => 'boots'], ['products' => [['sku' => 'sku-1']]], null);

        $executor = new ToolExecutor($this->toolExecutionService, [$tool]);
        $context = new AgentContext($this->buildSession(10), null);

        self::assertSame(
            ['products' => [['sku' => 'sku-1']]],
            $executor->execute('search_catalog', ['query' => 'boots'], $context)
        );
    }

    public function testExecuteThrowsWhenToolIsMissing(): void
    {
        $executor = new ToolExecutor($this->toolExecutionService, []);

        $this->expectException(LocalizedException::class);
        $executor->execute('missing_tool', [], new AgentContext($this->buildSession(10), null));
    }

    private function buildSession(int $sessionId): ChatSession
    {
        $session = $this->createMock(ChatSession::class);
        $session->method('getId')->willReturn($sessionId);

        return $session;
    }
}
