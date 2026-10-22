<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\GraphQl\ChatOutputFormatter;
use Perspective\AiCustomizationRequests\GraphQl\RequestScope;
use Perspective\AiCustomizationRequests\Model\Chat\ChatIdentity;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Request\RequestRepository;

class ResumeAiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly RequestScope $requestScope,
        private readonly AgentConfig $agentConfig,
        private readonly ChatSessionService $chatSessionService,
        private readonly ChatIdentity $identity,
        private readonly RequestRepository $requestRepository,
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
            $session = $this->chatSessionService->authorize(
                trim((string)$args['input']['session_uid']),
                (string)$args['input']['resume_token'],
                $storeId,
                $this->requestScope->customerId($context)
            );
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return $this->formatter->session(
            $session,
            $this->chatSessionService->getVisibleMessages($session),
            $this->requestRepository->findLatestForSession($session->getSessionId()),
            $this->identity->isEmailRequired($session)
        );
    }
}
