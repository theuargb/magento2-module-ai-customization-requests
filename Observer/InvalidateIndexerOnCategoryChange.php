<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Perspective\AiCustomizationRequests\Model\Indexer\ProductIndexerProcessor;

class InvalidateIndexerOnCategoryChange implements ObserverInterface
{
    public function __construct(
        private readonly ProductIndexerProcessor $productIndexerProcessor
    ) {
    }

    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getCategory() ?: $observer->getEvent()->getDataObject();
        $affectedProductIds = $this->extractAffectedProductIds($category);

        if ($affectedProductIds !== [] && !$this->productIndexerProcessor->isIndexerScheduled()) {
            $this->productIndexerProcessor->reindexList($affectedProductIds);
            return;
        }

        $this->productIndexerProcessor->markIndexerAsInvalid();
    }

    private function extractAffectedProductIds(mixed $category): array
    {
        if (!is_object($category) || !method_exists($category, 'getAffectedProductIds')) {
            return [];
        }

        $affectedProductIds = $category->getAffectedProductIds();
        if (!is_array($affectedProductIds) || $affectedProductIds === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', array_keys($affectedProductIds))));
        if ($ids !== []) {
            return $ids;
        }

        return array_values(array_filter(array_map('intval', $affectedProductIds)));
    }
}
