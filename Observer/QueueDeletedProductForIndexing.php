<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Perspective\AiCustomizationRequests\Model\Indexer\ProductIndexerProcessor;

class QueueDeletedProductForIndexing implements ObserverInterface
{
    public function __construct(
        private readonly ProductIndexerProcessor $productIndexerProcessor
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if ($product && $product->getId()) {
            $this->productIndexerProcessor->reindexRow((int)$product->getId());
        }
    }
}
