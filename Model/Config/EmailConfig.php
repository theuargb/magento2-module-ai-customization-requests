<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Config;

class EmailConfig
{
    private const ENABLED = ConfigReader::PREFIX . 'email/enabled';
    private const SENDER = ConfigReader::PREFIX . 'email/sender_identity';
    private const APPROVE_TEMPLATE = ConfigReader::PREFIX . 'email/approve_template';
    private const DECLINE_TEMPLATE = ConfigReader::PREFIX . 'email/decline_template';
    private const RESTORE_TTL_DAYS = ConfigReader::PREFIX . 'email/restore_link_ttl_days';

    public function __construct(private readonly ConfigReader $reader)
    {
    }

    public function isEnabled(int $storeId): bool
    {
        return $this->reader->flag(self::ENABLED, $storeId);
    }

    public function getSenderIdentity(int $storeId): string
    {
        return $this->reader->string(self::SENDER, $storeId);
    }

    public function getApproveTemplate(int $storeId): string
    {
        return $this->reader->string(self::APPROVE_TEMPLATE, $storeId);
    }

    public function getDeclineTemplate(int $storeId): string
    {
        return $this->reader->string(self::DECLINE_TEMPLATE, $storeId);
    }

    public function getRestoreLinkTtlDays(int $storeId): int
    {
        return $this->reader->int(self::RESTORE_TTL_DAYS, $storeId, 1, 90);
    }
}
