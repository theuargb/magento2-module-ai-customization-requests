<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Model\AbstractModel;

class CustomizationRequest extends AbstractModel
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\CustomizationRequest::class);
    }
}
