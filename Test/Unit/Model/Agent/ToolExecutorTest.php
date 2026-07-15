<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Test\Unit\Model\Agent;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Perspective\AiCustomizationRequests\Model\Agent\AgentContext;
use Perspective\AiCustomizationRequests\Model\Agent\Tool\ToolInterface;
use Perspective\AiCustomizationRequests\Model\Agent\ToolExecutor;
use Perspective\AiCustomizationRequests\Model\ChatSession;

class ToolExecutorTest extends TestCase
{
    public function testExecutesRegisteredTool(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('search_catalog');
        $tool->expects(self::once())->method('execute')->with(['query' => 'boots'])->willReturn(['products' => []]);
        $session = $this->createMock(ChatSession::class);
        self::assertSame(['products' => []], (new ToolExecutor([$tool]))->execute('search_catalog', ['query' => 'boots'], new AgentContext($session, null)));
    }

    public function testRejectsMissingTool(): void
    {
        $this->expectException(LocalizedException::class);
        (new ToolExecutor())->execute('missing', [], new AgentContext($this->createMock(ChatSession::class), null));
    }

    public function testBuildsSequentialToolDefinitionsForJsonSerialization(): void
    {
        $first = $this->createMock(ToolInterface::class);
        $first->method('getName')->willReturn('search_catalog');
        $first->method('getDescription')->willReturn('Searches products.');
        $first->method('getParameters')->willReturn(['type' => 'object']);

        $second = $this->createMock(ToolInterface::class);
        $second->method('getName')->willReturn('get_product_context');
        $second->method('getDescription')->willReturn('Loads a product.');
        $second->method('getParameters')->willReturn(['type' => 'object']);

        $definitions = (new ToolExecutor([$first, $second]))->getToolDefinitions();

        self::assertIsList($definitions);
        self::assertSame('search_catalog', $definitions[0]['function']['name']);
        self::assertSame('get_product_context', $definitions[1]['function']['name']);
    }
}
