<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ChatMessage extends AbstractDb
{
    public const TABLE = 'perspective_ai_chat_message';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'message_id');
    }
}
