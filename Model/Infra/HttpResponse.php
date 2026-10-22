<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Infra;

final class HttpResponse
{
    /** @param array<mixed> $body */
    public function __construct(
        public readonly int $status,
        public readonly array $body
    ) {
    }
}
