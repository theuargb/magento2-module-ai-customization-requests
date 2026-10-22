<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\GraphQl\ChatOutputFormatter;
use Perspective\AiCustomizationRequests\GraphQl\RequestScope;
use Perspective\AiCustomizationRequests\Model\Chat\ChatIdentity;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;

class StartAiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly RequestScope $requestScope,
        private readonly AgentConfig $agentConfig,
        private readonly ChatSessionService $chatSessionService,
        private readonly ChatIdentity $identity,
        private readonly ChatOutputFormatter $formatter
    ) {
    }

    /**
     * @param \Magento\GraphQl\Model\Query\ContextInterface $context
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<string, mixed>
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $storeId = $this->requestScope->storeId($context);
        if (!$this->agentConfig->isEnabled($storeId)) {
            throw new GraphQlInputException(__('AI chat is disabled.'));
        }

        try {
            $started = $this->chatSessionService->start($storeId, $this->requestScope->customerId($context));
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return $this->formatter->session(
            $started->session,
            [],
            null,
            $this->identity->isEmailRequired($started->session),
            $started->resumeToken
        );
    }
}
