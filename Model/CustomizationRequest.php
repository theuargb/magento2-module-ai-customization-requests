<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Model\AbstractModel;

class CustomizationRequest extends AbstractModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_NEW = 'new';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_PRODUCT_DRAFT_CREATED = 'product_draft_created';
    public const STATUS_QUOTE_DRAFT_CREATED = 'quote_draft_created';
    public const STATUS_QUOTE_SENT = 'quote_sent';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\CustomizationRequest::class);
    }
}
