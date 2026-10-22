<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Agent;

use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\Chat\PageContext;
use Perspective\AiCustomizationRequests\Model\Chat\ProductMention;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;

/**
 * All prompt text sent to the model lives here.
 */
class PromptBuilder
{
    public function system(ChatSession $session, ?CustomizationRequest $latestRequest): string
    {
        $lines = [
            'You are the storefront shopping assistant of this Magento shop.',
            'Recommend and mention only products returned by the search_catalog and get_product_context tools. Never invent catalog data, prices, availability, approval, product creation, or checkout.',
            'When the shopper wants a product customization:',
            '1. Identify the base product: use the explicitly selected product SKU first, otherwise the product of the current page if it is a product page.',
            '2. Collect concrete, specific requested changes before submitting anything.',
            '3. Then call save_customization_request.',
            'There is at most one pending customization request per chat: calling save_customization_request again while a request is pending updates it instead of creating a duplicate.',
            'Contact details (email) are handled by the storefront; never ask for them and never invent them.',
            'Submitted requests are reviewed by a shop administrator; never promise an outcome, a price, or a delivery date.',
        ];
        if ($latestRequest !== null) {
            $lines[] = sprintf(
                'Current customization request of this chat: uid "%s", title "%s", status "%s".',
                $latestRequest->getUid(),
                $latestRequest->getTitle(),
                $latestRequest->getStatus()->value
            );
        }

        return implode("\n", $lines);
    }

    /**
     * User content as sent to the model: shopper text followed by a bracketed context block for the
     * explicitly selected product and/or the resolved current page.
     *
     * @param ProductMention[] $mentions
     */
    public function userTurn(string $content, array $mentions, ?PageContext $pageContext): string
    {
        $contextLines = [];
        $selectedSkus = [];
        foreach ($mentions as $mention) {
            if ($mention->selected && !in_array($mention->sku, $selectedSkus, true)) {
                $selectedSkus[] = $mention->sku;
            }
        }
        if ($selectedSkus !== []) {
            $contextLines[] = 'The shopper explicitly selected catalog product SKU(s): ' . implode(', ', $selectedSkus) . '.';
        }
        if ($pageContext !== null) {
            $line = sprintf(
                'The shopper is currently viewing the storefront page "%s" (%s entity ID %d).',
                $pageContext->requestPath,
                $pageContext->entityType,
                $pageContext->entityId
            );
            if ($pageContext->productSku !== null) {
                $line .= sprintf(' It is the product page of SKU "%s"', $pageContext->productSku);
                if ($pageContext->productName !== null) {
                    $line .= sprintf(' ("%s")', $pageContext->productName);
                }
                $line .= '.';
            }
            $contextLines[] = $line;
        }
        if ($contextLines === []) {
            return $content;
        }

        return $content . "\n\n[Context: " . implode(' ', $contextLines) . ']';
    }

    public function emptyReplyFallback(): string
    {
        return 'I need a bit more detail to continue. Could you tell me more about what you are looking for?';
    }

    public function iterationLimitFallback(): string
    {
        return 'I could not finish that request right now. Please try again in a moment.';
    }
}
