<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

/** Storefront page the shopper was on when sending a message, resolved via URL rewrites. */
final class PageContext
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $requestPath,
        public readonly ?string $productSku = null,
        public readonly ?string $productName = null
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'request_path' => $this->requestPath,
            'product_sku' => $this->productSku,
            'product_name' => $this->productName,
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $type = (string)($data['entity_type'] ?? '');
        $id = (int)($data['entity_id'] ?? 0);
        $path = (string)($data['request_path'] ?? '');
        if ($type === '' || $id <= 0 || $path === '') {
            return null;
        }

        return new self(
            $type,
            $id,
            $path,
            isset($data['product_sku']) ? (string)$data['product_sku'] : null,
            isset($data['product_name']) ? (string)$data['product_name'] : null
        );
    }
}
