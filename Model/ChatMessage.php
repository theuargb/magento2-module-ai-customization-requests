<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Model\AbstractModel;

class ChatMessage extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ChatMessage::class);
    }
}
