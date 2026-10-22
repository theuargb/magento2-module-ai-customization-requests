<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\Request\RequestHistory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory as Resource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(RequestHistory::class, Resource::class);
    }
}
