<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

class AgentConfig
{
    private const ENABLED = ConfigReader::PREFIX . 'general/enabled';
    private const MAX_ITERATIONS = ConfigReader::PREFIX . 'agent/max_iterations';
    private const MAX_MESSAGE_LENGTH = ConfigReader::PREFIX . 'agent/max_message_length';
    private const SESSION_TTL_HOURS = ConfigReader::PREFIX . 'agent/session_ttl_hours';

    public function __construct(private readonly ConfigReader $reader)
    {
    }

    public function isEnabled(int $storeId): bool
    {
        return $this->reader->flag(self::ENABLED, $storeId);
    }

    public function getMaxIterations(int $storeId): int
    {
        return $this->reader->int(self::MAX_ITERATIONS, $storeId, 1, 20);
    }

    public function getMaxMessageLength(int $storeId): int
    {
        return $this->reader->int(self::MAX_MESSAGE_LENGTH, $storeId, 1, 10000);
    }

    public function getSessionTtlHours(int $storeId): int
    {
        return $this->reader->int(self::SESSION_TTL_HOURS, $storeId, 1, 720);
    }
}
