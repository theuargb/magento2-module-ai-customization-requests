<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomizationRequest extends AbstractDb
{
    public const TABLE = 'perspective_ai_customization_request';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'request_id');
    }
}
