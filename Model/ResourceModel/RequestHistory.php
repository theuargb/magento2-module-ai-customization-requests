<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RequestHistory extends AbstractDb
{
    public const TABLE = 'perspective_ai_request_history';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'history_id');
    }
}
