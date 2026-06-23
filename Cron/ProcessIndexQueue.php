<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Cron;

use Psr\Log\LoggerInterface;
use Perspective\AiCustomizationRequests\Model\Indexer\ProductIndexer;

class ProcessIndexQueue
{
    public function __construct(
        private readonly ProductIndexer $productIndexer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $this->productIndexer->process();
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
