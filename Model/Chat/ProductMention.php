<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

/** A catalog product shown with a message; `selected` marks the shopper's explicit choice. */
final class ProductMention
{
    public function __construct(
        public readonly string $sku,
        public readonly bool $selected = false
    ) {
    }

    /** @return array{sku: string, selected: bool} */
    public function toArray(): array
    {
        return ['sku' => $this->sku, 'selected' => $this->selected];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $sku = trim((string)($data['sku'] ?? ''));

        return $sku === '' ? null : new self($sku, (bool)($data['selected'] ?? false));
    }
}
