<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use RuntimeException;

/**
 * A token was not minted: the model cannot hold tokens, the signed-in user may not manage the tokens
 * of another tokenable, the password was not confirmed a moment ago, tokens are minted too fast or
 * the tokenable holds as many as allowed, the lifetime asked for is none of the allowed ones, or the
 * name is empty or longer than a token's name may be.
 */
final class MintRefused extends RuntimeException
{
    /**
     * @param  'not_tokenable'|'not_allowed'|'password_not_confirmed'|'too_fast'|'limit_reached'|'lifetime'|'name'  $reason
     */
    private function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $retryAfter = null,
        public readonly ?int $limit = null,
    ) {
        parent::__construct($message);
    }

    public static function notTokenable(): self
    {
        return new self('not_tokenable', 'Tokens are minted for an Eloquent model that uses Laravel\Sanctum\HasApiTokens: the signed-in user, or the tokenable given.');
    }

    public static function notAllowed(string $ability): self
    {
        return new self('not_allowed', "The signed-in user may not manage the tokens of this tokenable. The application allows it with the Gate ability [{$ability}].");
    }

    public static function passwordNotConfirmed(): self
    {
        return new self('password_not_confirmed', 'The self-service mints a token only behind a password confirmed within the timeout. Put the route behind password.confirm.');
    }

    public static function tooFast(int $retryAfter): self
    {
        return new self('too_fast', "Tokens are minted too fast. Try again in {$retryAfter} seconds.", retryAfter: $retryAfter);
    }

    public static function limitReached(int $limit): self
    {
        return new self('limit_reached', "The user holds {$limit} tokens that have not expired, as many as allowed.", limit: $limit);
    }

    public static function name(int $longest): self
    {
        return new self('name', "A token needs a name of 1 to {$longest} characters.");
    }

    public static function lifetime(int $days): self
    {
        return new self('lifetime', $days === 0 ? 'A token without an expiry is not allowed.' : "A token cannot live for {$days} days. Choose one of the configured lifetimes.");
    }
}
