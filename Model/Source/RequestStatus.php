<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Perspective\AiCustomizationRequests\Model\CustomizationRequest;

class RequestStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => CustomizationRequest::STATUS_PENDING_REVIEW, 'label' => __('Pending Review')],
            ['value' => CustomizationRequest::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => CustomizationRequest::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => CustomizationRequest::STATUS_DECLINED, 'label' => __('Declined')],
        ];
    }
}
