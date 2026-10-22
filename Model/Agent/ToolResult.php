<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;

/** Tool output: `payload` goes to the model; mentions and request are side effects for the shopper UI. */
final class ToolResult
{
    /**
     * @param array<mixed> $payload
     * @param ProductMention[] $mentions
     */
    public function __construct(
        public readonly array $payload,
        public readonly array $mentions = [],
        public readonly ?CustomizationRequest $request = null
    ) {
    }
}
