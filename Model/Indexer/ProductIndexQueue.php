<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Indexer;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\AiCustomizationRequests\Model\IndexQueue;
use Perspective\AiCustomizationRequests\Model\IndexQueueFactory;
use Perspective\AiCustomizationRequests\Model\ResourceModel\IndexQueue as IndexQueueResource;
use Perspective\AiCustomizationRequests\Model\ResourceModel\IndexQueue\CollectionFactory as IndexQueueCollectionFactory;

class ProductIndexQueue
{
    public function __construct(
        private readonly IndexQueueFactory $indexQueueFactory,
        private readonly IndexQueueResource $indexQueueResource,
        private readonly IndexQueueCollectionFactory $indexQueueCollectionFactory,
        private readonly DateTime $dateTime
    ) {
    }

    public function enqueue(int $productId, string $operation = 'upsert'): void
    {
        $collection = $this->indexQueueCollectionFactory->create();
        $collection->addFieldToFilter('product_id', $productId);
        $collection->setPageSize(1);

        /** @var IndexQueue $queue */
        $queue = $collection->getFirstItem();
        if (!$queue->getId()) {
            $queue = $this->indexQueueFactory->create();
            $queue->setData('product_id', $productId);
        }

        $queue->addData([
            'operation' => $operation,
            'status' => IndexQueue::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'scheduled_at' => $this->dateTime->gmtDate(),
        ]);
        $this->indexQueueResource->save($queue);
    }
}
