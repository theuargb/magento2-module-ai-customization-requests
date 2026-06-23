<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Indexer;

use Magento\Framework\Indexer\AbstractProcessor;

class ProductIndexerProcessor extends AbstractProcessor
{
    public const INDEXER_ID = 'perspective_ai_product_rag';
}
