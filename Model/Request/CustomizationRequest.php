<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Request;

use Magento\Framework\Model\AbstractModel;
use Perspective\AiCustomizationRequests\Model\Infra\JsonCodec;
use Perspective\AiCustomizationRequests\Model\ResourceModel\CustomizationRequest as CustomizationRequestResource;

/** Status changes go only through RequestRepository::transition(). */
class CustomizationRequest extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(CustomizationRequestResource::class);
    }

    public function initialize(string $uid, ?int $sessionId, int $storeId, ?int $customerId, string $customerEmail): void
    {
        $this->setData([
            'request_uid' => $uid,
            'session_id' => $sessionId,
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'status' => RequestStatus::PendingReview->value,
        ]);
    }

    public function getRequestId(): int
    {
        return (int)$this->getId();
    }

    public function getUid(): string
    {
        return (string)$this->getData('request_uid');
    }

    public function getSessionId(): ?int
    {
        $id = $this->getData('session_id');

        return $id === null ? null : (int)$id;
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function getCustomerId(): ?int
    {
        $id = $this->getData('customer_id');

        return $id === null ? null : (int)$id;
    }

    public function getCustomerEmail(): string
    {
        return (string)$this->getData('customer_email');
    }

    public function getBaseProductId(): ?int
    {
        $id = $this->getData('base_product_id');

        return $id === null ? null : (int)$id;
    }

    public function getBaseProductSku(): string
    {
        return (string)$this->getData('base_product_sku');
    }

    public function setBaseProduct(int $productId, string $sku): void
    {
        $this->addData(['base_product_id' => $productId, 'base_product_sku' => $sku]);
    }

    public function getTitle(): string
    {
        return (string)$this->getData('title');
    }

    public function getSummary(): string
    {
        return (string)$this->getData('summary');
    }

    /** @return array<mixed> */
    public function getRequestedChanges(): array
    {
        return JsonCodec::decodeArray($this->getData('requested_changes_json'));
    }

    /** @param array<mixed> $requestedChanges */
    public function setDetails(string $title, string $summary, array $requestedChanges): void
    {
        $this->addData([
            'title' => $title,
            'summary' => $summary,
            'requested_changes_json' => JsonCodec::encode($requestedChanges),
        ]);
    }

    public function getStatus(): RequestStatus
    {
        return RequestStatus::from((string)$this->getData('status'));
    }

    /** @internal Use RequestRepository::transition(). */
    public function applyStatus(RequestStatus $status): void
    {
        $this->setData('status', $status->value);
    }

    public function getCustomProductId(): ?int
    {
        $id = $this->getData('custom_product_id');

        return $id === null ? null : (int)$id;
    }

    public function getCustomProductSku(): ?string
    {
        $sku = (string)$this->getData('custom_product_sku');

        return $sku === '' ? null : $sku;
    }

    public function setCustomProduct(int $productId, string $sku): void
    {
        $this->addData(['custom_product_id' => $productId, 'custom_product_sku' => $sku]);
    }

    public function setRestoreToken(string $tokenHash, int $ttlDays): void
    {
        $this->addData([
            'restore_token_hash' => $tokenHash,
            'restore_expires_at' => gmdate('Y-m-d H:i:s', time() + $ttlDays * 86400),
            'restore_consumed_at' => null,
        ]);
    }

    public function isRestorable(): bool
    {
        $expiresAt = (string)$this->getData('restore_expires_at');

        return $this->getStatus() === RequestStatus::Approved
            && $this->getCustomProductId() !== null
            && $expiresAt !== ''
            && strtotime($expiresAt . ' UTC') >= time()
            && !$this->getData('restore_consumed_at');
    }

    public function markRestoreConsumed(): void
    {
        $this->setData('restore_consumed_at', gmdate('Y-m-d H:i:s'));
    }

    public function getDecisionNote(): ?string
    {
        $note = (string)$this->getData('decision_note');

        return $note === '' ? null : $note;
    }

    public function setDecision(?string $note, int $adminUserId): void
    {
        $this->addData(['decision_note' => $note, 'decided_by_user_id' => $adminUserId]);
    }

    public function getNotifiedAt(): ?string
    {
        $value = (string)$this->getData('notified_at');

        return $value === '' ? null : $value;
    }

    public function markNotified(): void
    {
        $this->setData('notified_at', gmdate('Y-m-d H:i:s'));
    }

    public function getCreatedAt(): string
    {
        return (string)$this->getData('created_at');
    }
}
