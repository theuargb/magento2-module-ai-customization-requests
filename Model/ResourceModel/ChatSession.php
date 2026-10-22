<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ChatSession extends AbstractDb
{
    public const TABLE = 'perspective_ai_chat_session';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'session_id');
    }
}
