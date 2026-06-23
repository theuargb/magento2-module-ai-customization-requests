<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IndexQueue extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('perspective_ai_index_queue', 'queue_id');
    }
}
