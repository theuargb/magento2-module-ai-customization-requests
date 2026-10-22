<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\Chat\ChatMessage;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatMessage as Resource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ChatMessage::class, Resource::class);
    }
}
