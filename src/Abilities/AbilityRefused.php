<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Abilities;

use InvalidArgumentException;

/**
 * A token was to carry abilities it may not have: none, the wildcard, one the catalog does not know,
 * or a reserved one through the self-service.
 *
 * Refused rather than dropped. A token minted with fewer abilities than were asked for looks like a
 * working token until the first request it cannot make.
 */
final class AbilityRefused extends InvalidArgumentException
{
    /**
     * @param  'none'|'wildcard'|'unknown'|'reserved'|'malformed'  $reason
     * @param  list<string>  $abilities  the abilities the refusal is about
     */
    private function __construct(
        public readonly string $reason,
        public readonly array $abilities,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function none(): self
    {
        return new self('none', [], 'A token has to carry at least one ability.');
    }

    public static function wildcard(): self
    {
        return new self('wildcard', [AbilityCatalog::WILDCARD], 'A token cannot carry the wildcard. Name the abilities it needs.');
    }

    /** @param  list<string>  $abilities */
    public static function unknown(array $abilities): self
    {
        return new self('unknown', $abilities, 'The ability catalog does not know ['.implode(', ', $abilities).'].');
    }

    /** @param  list<string>  $abilities */
    public static function reserved(array $abilities): self
    {
        return new self('reserved', $abilities, '['.implode(', ', $abilities).'] is reserved for the application\'s own code and cannot be minted through the self-service.');
    }

    public static function malformed(string $ability): self
    {
        return new self('malformed', [$ability], "[{$ability}] is no name an ability can have: the wildcard, an empty name and one with whitespace, a comma or an asterisk are refused.");
    }
}
