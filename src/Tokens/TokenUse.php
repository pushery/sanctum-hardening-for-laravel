<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;

/**
 * One request a token made: the route's name, or its URI pattern where it has none, the method, the
 * status of the answer and the time.
 */
final readonly class TokenUse
{
    public function __construct(
        public string $method,
        public string $route,
        public int $status,
        public DateTimeImmutable $usedAt,
    ) {}
}
