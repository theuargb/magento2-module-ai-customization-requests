<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;

final class TurnResult
{
    public function __construct(
        public readonly ChatSession $session,
        public readonly ChatMessage $userMessage,
        public readonly ChatMessage $reply,
        public readonly ?CustomizationRequest $request
    ) {
    }
}
