<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Cron;

use Perspective\AiCustomizationRequests\Model\Chat\ChatSessionRepository;

class CleanExpiredSessions
{
    public function __construct(private readonly ChatSessionRepository $sessionRepository)
    {
    }

    public function execute(): void
    {
        $this->sessionRepository->deleteExpired();
    }
}
