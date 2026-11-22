<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;

/**
 * The record of one revoked token: which token it was, whose, who revoked it and when.
 *
 * The token's name is the one it had when it was revoked, and `keyPrefix` the characters its key starts
 * with, up to and including the key's identifier, so the record still names the token and its key after
 * the token is pruned. A token minted outside the package has no key prefix. `revokedByType` and `revokedById` are the morph class and the key of the signed-in
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
        public ?string $keyPrefix = null,
    ) {}
}
