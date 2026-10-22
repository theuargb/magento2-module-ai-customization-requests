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
use Perspective\AiCustomizationRequests\GraphQl\RequestScope;
use Perspective\AiCustomizationRequests\Model\Config\AgentConfig;
use Perspective\AiCustomizationRequests\Model\Request\CartRestorer;

class RestoreAiPreparedCart implements ResolverInterface
{
    public function __construct(
        private readonly RequestScope $requestScope,
        private readonly AgentConfig $agentConfig,
        private readonly CartRestorer $cartRestorer
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
            $cartUrl = $this->cartRestorer->restore(trim((string)$args['token']), $storeId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        return ['cart_url' => $cartUrl];
    }
}
