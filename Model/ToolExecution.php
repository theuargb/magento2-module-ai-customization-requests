<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model;

use Magento\Framework\Model\AbstractModel;

class ToolExecution extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ToolExecution::class);
    }
}
