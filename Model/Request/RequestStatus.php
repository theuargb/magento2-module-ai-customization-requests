<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\Phrase;

/** Request lifecycle; `canTransitionTo` is the single source of allowed moves. */
enum RequestStatus: string
{
    case PendingReview = 'pending_review';
    case Processing = 'processing';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): Phrase
    {
        return match ($this) {
            self::PendingReview => __('Pending Review'),
            self::Processing => __('Processing'),
            self::Approved => __('Approved'),
            self::Declined => __('Declined'),
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PendingReview => $target === self::Processing || $target === self::Declined,
            self::Processing => $target === self::Approved || $target === self::PendingReview,
            self::Approved, self::Declined => false,
        };
    }
}
