<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\ToolExecution;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\ToolExecution as Model;
use Perspective\AiCustomizationRequests\Model\ResourceModel\ToolExecution as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
