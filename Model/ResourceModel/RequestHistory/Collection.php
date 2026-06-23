<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\RequestHistory as Model;
use Perspective\AiCustomizationRequests\Model\ResourceModel\RequestHistory as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
