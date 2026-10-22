<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Perspective\AiCustomizationRequests\Model\Chat\ChatMessage;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;

final class AgentResult
{
    public function __construct(
        public readonly ChatMessage $reply,
        public readonly ?CustomizationRequest $request
    ) {
    }
}
