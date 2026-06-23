<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Model\AbstractModel;

class IndexQueue extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\IndexQueue::class);
    }
}
