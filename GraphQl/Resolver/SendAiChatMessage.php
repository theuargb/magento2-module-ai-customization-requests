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
use Perspective\AiCustomizationRequests\Model\Chat\ChatTurnService;
use Perspective\AiCustomizationRequests\Model\Chat\SendTurnCommand;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;

class SendAiChatMessage implements ResolverInterface
{
    public function __construct(
        private readonly RequestScope $requestScope,
        private readonly AgentConfig $agentConfig,
        private readonly ChatTurnService $chatTurnService,
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

        $input = $args['input'];
        $command = new SendTurnCommand(
            $storeId,
            $this->requestScope->customerId($context),
            trim((string)$input['session_uid']),
            (string)$input['resume_token'],
            (string)$input['client_message_id'],
            (string)$input['message'],
            self::optional($input, 'selected_product_sku'),
            self::optional($input, 'current_page_url'),
            self::optional($input, 'guest_email')
        );

        try {
            $turn = $this->chatTurnService->send($command);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return [
            'user_message' => $this->formatter->message($turn->userMessage),
            'assistant_message' => $this->formatter->message($turn->reply),
            'request_uid' => $turn->request?->getUid(),
            'email_required' => $this->identity->isEmailRequired($turn->session),
        ];
    }

    /**
     * Trimmed optional input string, null when absent or empty.
     *
     * @param array<string, mixed> $input
     */
    private static function optional(array $input, string $key): ?string
    {
        $value = trim((string)($input[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
