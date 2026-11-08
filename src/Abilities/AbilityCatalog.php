<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Abilities;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;

/**
 * Every ability a token can carry, and the one place an ability exists.
 *
 * Minting checks what a token asks for against it, and the self-service offers and labels what it
 * holds. There is no wildcard: a token names what it may do. A token only ever narrows what its user
 * may do anyway, so granting one is safe even for a user who may do a lot.
 *
 * An ability comes from `sanctum-hardening.abilities`, ability => label, or from define(), for an
 * application that registers its abilities in a service provider. A reserved ability exists for the
 * application's own code, a device token for instance: the self-service neither offers it nor lists
 * or revokes a token that carries one.
 */
final class AbilityCatalog
{
    public const string WILDCARD = '*';

    /** @var array<string, array{label: string, reserved: bool}> */
    private array $defined = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Translator $translator,
    ) {}

    /**
     * Define an ability in code, beside the ones the configuration names. A definition here wins over
     * the configuration's entry for the same ability.
     *
     * @throws AbilityRefused for the wildcard or a name with whitespace in it
     */
    public function define(string $ability, ?string $label = null, bool $reserved = false): void
    {
        if (! $this->isName($ability)) {
            throw AbilityRefused::malformed($ability);
        }

        $this->defined[$ability] = ['label' => $label ?? $ability, 'reserved' => $reserved];
    }

    /**
     * Every ability, the reserved ones included.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->entries());
    }

    /**
     * The abilities a person may grant through the self-service.
     *
     * @return list<string>
     */
    public function grantable(): array
    {
        return array_keys(array_filter($this->entries(), static fn (array $entry): bool => ! $entry['reserved']));
    }

    public function has(string $ability): bool
    {
        return array_key_exists($ability, $this->entries());
    }

    public function isReserved(string $ability): bool
    {
        return ($this->entries()[$ability] ?? ['reserved' => false])['reserved'];
    }

    /** The label of an ability as a person reads it: its translation, or the text the catalog was given. */
    public function label(string $ability): string
    {
        $label = ($this->entries()[$ability] ?? ['label' => $ability])['label'];
        $translated = $this->translator->get($label);

        return is_string($translated) ? $translated : $label;
    }

    /**
     * The abilities a token is to be minted with, each checked: at least one, no wildcard, none the
     * catalog does not know, and none reserved unless the application's own code mints the token.
     *
     * @param  array<array-key, mixed>  $abilities
     * @return list<string>
     *
     * @throws AbilityRefused
     */
    public function check(array $abilities, bool $system = false): array
    {
        $names = array_values(array_unique(array_map(static fn (mixed $ability): string => is_string($ability) ? $ability : get_debug_type($ability), $abilities)));

        if ($names === []) {
            throw AbilityRefused::none();
        }

        if (in_array(self::WILDCARD, $names, true)) {
            throw AbilityRefused::wildcard();
        }

        $unknown = array_values(array_filter($names, fn (string $ability): bool => ! $this->has($ability)));

        if ($unknown !== []) {
            throw AbilityRefused::unknown($unknown);
        }

        $reserved = array_values(array_filter($names, $this->isReserved(...)));

        if (! $system && $reserved !== []) {
            throw AbilityRefused::reserved($reserved);
        }

        return $names;
    }

    /**
     * Whether a list of abilities holds one that is reserved, so a token that carries it stays out of
     * the self-service.
     *
     * @param  array<array-key, mixed>  $abilities
     */
    public function holdsReserved(array $abilities): bool
    {
        return array_any($abilities, fn (mixed $ability): bool => is_string($ability) && $this->isReserved($ability));
    }

    /** @return array<string, array{label: string, reserved: bool}> */
    private function entries(): array
    {
        $entries = [];
        $configured = $this->config->get('sanctum-hardening.abilities', []);

        // A list entry names the ability alone; a keyed one gives it a label.
        foreach (is_array($configured) ? $configured : [] as $ability => $label) {
            $name = is_int($ability) ? $label : $ability;

            if (is_string($name) && $this->isName($name)) {
                $entries[$name] = ['label' => is_string($label) ? $label : $name, 'reserved' => false];
            }
        }

        $reserved = $this->config->get('sanctum-hardening.reserved', []);

        foreach (is_array($reserved) ? $reserved : [] as $ability) {
            if (is_string($ability) && $this->isName($ability)) {
                $entries[$ability] = ['label' => ($entries[$ability] ?? ['label' => $ability])['label'], 'reserved' => true];
            }
        }

        return [...$entries, ...$this->defined];
    }

    /** A name an ability can have: not the wildcard, not empty, and nothing a list of them would split. */
    private function isName(string $ability): bool
    {
        return $ability !== self::WILDCARD && preg_match('/^[^\s,*]+$/u', $ability) === 1;
    }
}
