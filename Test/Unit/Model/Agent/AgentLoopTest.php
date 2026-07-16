<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Test\Unit\Model\Agent;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Perspective\AiCustomizationRequests\Model\Agent\AgentLoop;
use Perspective\AiCustomizationRequests\Model\Agent\ToolExecutor;
use Perspective\AiCustomizationRequests\Model\ChatMessage;
use Perspective\AiCustomizationRequests\Model\ChatSession;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\ModuleConfig;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;
use Perspective\AiCustomizationRequests\Model\OpenRouter\Client as OpenRouterClient;

class AgentLoopTest extends TestCase
{
    public function testSendsSelectedProductSkuToModelWithUserMessage(): void
    {
        $session = $this->createMock(ChatSession::class);
        $session->method('getId')->willReturn(42);
        $session->method('getData')->willReturnCallback(static fn(string $key): mixed => match ($key) {
            'store_id' => 1,
            'customer_email' => null,
            default => null,
        });

        $message = $this->createMock(ChatMessage::class);
        $message->method('getData')->willReturnCallback(static fn(string $key): mixed => match ($key) {
            'role' => 'user',
            'content' => 'Make this one green.',
            default => null,
        });

        $chatSessionService = $this->createMock(ChatSessionService::class);
        $chatSessionService->method('getMessages')->with($session)->willReturn([$message]);
        $chatSessionService->method('getProductMentions')->with($message)->willReturn([[
            'sku' => 'Red Cup',
            'selected' => true,
        ]]);
        $chatSessionService->method('getPageContext')->with($message)->willReturn([
            'entity_type' => 'product',
            'entity_id' => 42,
            'request_path' => 'red-cup.html',
            'product_sku' => 'Red Cup',
            'product_name' => 'Red Cup',
        ]);

        $moduleConfig = $this->createMock(ModuleConfig::class);
        $moduleConfig->method('getChatModel')->with(1)->willReturn('test-model');
        $moduleConfig->method('getMaxIterations')->with(1)->willReturn(1);

        $openRouterClient = $this->createMock(OpenRouterClient::class);
        $openRouterClient->expects(self::once())
            ->method('chatCompletion')
            ->with(
                self::callback(static function (array $messages): bool {
                    self::assertStringContainsString('Make this one green.', $messages[1]['content']);
                    self::assertStringContainsString('Red Cup', $messages[1]['content']);
                    self::assertStringContainsString('Do not ask for its SKU', $messages[1]['content']);
                    self::assertStringContainsString('red-cup.html', $messages[1]['content']);
                    self::assertStringContainsString('product entity ID 42', $messages[1]['content']);
                    self::assertStringContainsString('product SKU: Red Cup', $messages[1]['content']);

                    return true;
                }),
                [],
                'test-model',
                1
            )
            ->willReturn(['choices' => [['message' => ['content' => 'I can help with that.']]]]);

        $customizationRequestService = $this->createMock(CustomizationRequestService::class);
        $customizationRequestService->method('getLatestForSession')->with(42)->willReturn(null);

        $result = (new AgentLoop(
            $openRouterClient,
            $moduleConfig,
            new ToolExecutor(),
            $chatSessionService,
            $customizationRequestService,
            $this->createMock(Json::class)
        ))->run($session);

        self::assertSame('I can help with that.', $result['content']);
    }
}
