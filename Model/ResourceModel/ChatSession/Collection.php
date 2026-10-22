<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\Chat\ChatSession;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ChatSession as Resource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ChatSession::class, Resource::class);
    }
}
