<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\AiCustomizationRequests\Model\Request\CustomizationRequest;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as Resource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CustomizationRequest::class, Resource::class);
    }
}
