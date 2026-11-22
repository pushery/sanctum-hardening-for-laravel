<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

/**
 * The public parts of a key that KeyFormat::parse() read. The secret is left out on purpose: what a
 * caller logs or shows from here never carries it.
 */
final readonly class ParsedKey
{
    public function __construct(
        /** 2 to 10 letters a-z. */
        public string $product,
        /** `live` or `test`. */
        public string $environment,
        /** 1 to 32 characters a-z and 0-9, or null for a key without a tenant. */
        public ?string $tenant,
        /** The public identifier: 12 characters a-z and 0-9. */
        public string $id,
    ) {}

    /**
     * The key up to and including its identifier, `acme_live_l9qjzywdesvy`: the characters the key starts with, which tell
     * it apart from the holder's other keys and give away nothing of the secret.
     */
    public function prefix(): string
    {
        return $this->product.'_'.$this->environment.'_'.($this->tenant === null ? '' : $this->tenant.'_').$this->id;
    }
}
