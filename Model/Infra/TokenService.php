<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Infra;

/** Public identifiers and bearer secrets. Secrets are only ever persisted as sha256 hashes. */
class TokenService
{
    public function uid(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function secret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function matches(string $secret, string $hash): bool
    {
        return $secret !== '' && $hash !== '' && hash_equals($hash, $this->hash($secret));
    }
}
