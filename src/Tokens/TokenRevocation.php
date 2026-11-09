<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;

/**
 * The record of one revoked token: which token it was, whose, who revoked it and when.
 *
 * The token's name is the one it had when it was revoked, so the record still names it after the
 * token is pruned. `revokedByType` and `revokedById` are the morph class and the key of the signed-in
 * user who revoked it, and null where no signed-in user did: the application's own code, or a command.
 */
final readonly class TokenRevocation
{
    public function __construct(
        public string $tokenId,
        public string $tokenName,
        public string $tokenableType,
        public string $tokenableId,
        public ?string $revokedByType,
        public ?string $revokedById,
        public DateTimeImmutable $revokedAt,
    ) {}
}
