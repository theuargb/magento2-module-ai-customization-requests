<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\IndexQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\IndexQueue as Model;
use Perspective\AiCustomizationRequests\Model\ResourceModel\IndexQueue as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
