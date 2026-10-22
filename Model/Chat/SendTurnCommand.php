<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

final class SendTurnCommand
{
    public function __construct(
        public readonly int $storeId,
        public readonly ?int $customerId,
        public readonly string $sessionUid,
        public readonly string $resumeToken,
        public readonly string $clientMessageId,
        public readonly string $message,
        public readonly ?string $selectedProductSku,
        public readonly ?string $currentPageUrl,
        public readonly ?string $guestEmail
    ) {
    }
}
