<?php
declare(strict_types=1);

namespace Perspective\AiCustomizationRequests\Model\Chat;

/** A new session plus its resume token; the token is never stored in plain text. */
final class StartedSession
{
    public function __construct(
        public readonly ChatSession $session,
        public readonly string $resumeToken
    ) {
    }
}
