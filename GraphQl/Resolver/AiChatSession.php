<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\GraphQl\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Perspective\AiCustomizationRequests\Model\ChatSessionService;
use Perspective\AiCustomizationRequests\Model\CustomizationRequestService;

class AiChatSession implements ResolverInterface
{
    public function __construct(
        private readonly ChatSessionService $chatSessionService,
        private readonly CustomizationRequestService $customizationRequestService
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $session = $this->chatSessionService->requireByUid((string)$args['session_uid']);
        $request = $this->customizationRequestService->getLatestForSession((int)$session->getId());

        return $this->chatSessionService->formatSession($session, $request);
    }
}
