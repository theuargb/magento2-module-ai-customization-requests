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
            ['value' => CustomizationRequest::STATUS_DRAFT, 'label' => __('Draft')],
            ['value' => CustomizationRequest::STATUS_NEW, 'label' => __('New')],
            ['value' => CustomizationRequest::STATUS_UNDER_REVIEW, 'label' => __('Under Review')],
            ['value' => CustomizationRequest::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => CustomizationRequest::STATUS_DECLINED, 'label' => __('Declined')],
            ['value' => CustomizationRequest::STATUS_PRODUCT_DRAFT_CREATED, 'label' => __('Product Draft Created')],
            ['value' => CustomizationRequest::STATUS_QUOTE_DRAFT_CREATED, 'label' => __('Quote Draft Created')],
            ['value' => CustomizationRequest::STATUS_QUOTE_SENT, 'label' => __('Quote Sent')],
        ];
    }
}
