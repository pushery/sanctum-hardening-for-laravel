<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Abilities;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;

/**
 * Every ability a token can carry, and the one place an ability exists.
 *
 * Minting checks what a token asks for against it, and the self-service offers and labels what it
 * holds. There is no wildcard: a token names what it may do. A token only ever narrows what its user
 * may do anyway, so granting one is safe even for a user who may do a lot.
 *
 * An ability comes from `sanctum-hardening.abilities`, ability => label, or from define(), for an
 * application that registers its abilities in a service provider. An ability can name the holders
 * that may carry it, by morph class or class name, where it only means something for a workspace,
 * say, and not for a user: the self-service offers it to those holders alone and refuses a mint for
 * another. A reserved ability exists for the
 * application's own code, a device token for instance: the self-service neither offers it nor lists
 * or revokes a token that carries one.
 */
final class AbilityCatalog
{
    public const string WILDCARD = '*';

    /** @var array<string, array{label: string, reserved: bool, holders: list<string>}> */
    private array $defined = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Translator $translator,
    ) {}

    /**
     * Define an ability in code, beside the ones the configuration names. A definition here wins over
     * the configuration's entry for the same ability.
     *
     * @param  list<string>  $holders  the morph classes or class names of the holders that may carry it, none for any
     *
     * @throws AbilityRefused for the wildcard or a name with whitespace in it
     */
    public function define(string $ability, ?string $label = null, bool $reserved = false, array $holders = []): void
    {
        if (! $this->isName($ability)) {
            throw AbilityRefused::malformed($ability);
        }

        $this->defined[$ability] = ['label' => $label ?? $ability, 'reserved' => $reserved, 'holders' => $this->holders($holders)];
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
     * The abilities a person may grant through the self-service, to `$holder` where one is named: an ability
     * that names its holders is offered to those alone.
     *
     * @return list<string>
     */
    public function grantable(?Model $holder = null): array
    {
        return array_keys(array_filter($this->entries(), fn (array $entry): bool => ! $entry['reserved'] && $this->carries($entry['holders'], $holder)));
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
     * catalog does not know, none reserved unless the application's own code mints the token, and,
     * where `$holder` is named, none that names other holders.
     *
     * @param  array<array-key, mixed>  $abilities
     * @return list<string>
     *
     * @throws AbilityRefused
     */
    public function check(array $abilities, bool $system = false, ?Model $holder = null): array
    {
        $names = array_values(array_unique(array_map($this->abilityName(...), $abilities)));

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

        $foreign = $holder instanceof Model
            ? array_values(array_filter($names, fn (string $ability): bool => ! $this->carries($this->entries()[$ability]['holders'] ?? [], $holder)))
            : [];

        if ($foreign !== []) {
            throw AbilityRefused::notForHolder($foreign);
        }

        return $names;
    }

    /**
     * An ability as a message about it names it: a name as it is written, anything else by its type.
     *
     * @param  mixed  $ability  an ability as the caller hands it over, which may be any value
     */
    private function abilityName(mixed $ability): string
    {
        return is_string($ability) ? $ability : get_debug_type($ability);
    }

    /**
     * Whether a list of abilities holds one that is reserved, so a token that carries it stays out of
     * the self-service.
     *
     * @param  array<array-key, mixed>  $abilities
     */
    public function holdsReserved(array $abilities): bool
    {
        return array_any(array_filter($abilities, is_string(...)), $this->isReserved(...));
    }

    /**
     * Whether `$holder` may carry an ability that names `$holders`: any holder where it names none, otherwise one
     * whose morph class or class it names.
     *
     * @param  list<string>  $holders
     */
    private function carries(array $holders, ?Model $holder): bool
    {
        return $holders === [] || ($holder instanceof Model && array_any($holders, static fn (string $kind): bool => $holder->getMorphClass() === $kind || $holder instanceof $kind));
    }

    /**
     * The holders an entry names, each a morph class or a class name; anything else is left out.
     *
     * @param  mixed  $holders  the holders as an entry of the configuration names them, which a host may write as any type
     * @return list<string>
     */
    private function holders(mixed $holders): array
    {
        return array_values(array_filter(array_filter(is_array($holders) ? $holders : [], is_string(...)), static fn (string $holder): bool => $holder !== ''));
    }

    /** @return array<string, array{label: string, reserved: bool, holders: list<string>}> */
    private function entries(): array
    {
        $entries = [];
        $configured = $this->config->get('sanctum-hardening.abilities', []);

        // A list entry names the ability alone; a keyed one gives it a label, or a label and the holders that may
        // carry it.
        foreach (is_array($configured) ? $configured : [] as $ability => $entry) {
            $name = is_int($ability) ? $entry : $ability;
            $label = is_array($entry) ? ($entry['label'] ?? null) : $entry;

            if (is_string($name) && $this->isName($name)) {
                $entries[$name] = ['label' => is_string($label) ? $label : $name, 'reserved' => false, 'holders' => $this->holders(is_array($entry) ? ($entry['holders'] ?? []) : [])];
            }
        }

        $reserved = $this->config->get('sanctum-hardening.reserved', []);

        foreach (is_array($reserved) ? $reserved : [] as $ability) {
            if (is_string($ability) && $this->isName($ability)) {
                $entries[$ability] = ['label' => ($entries[$ability] ?? ['label' => $ability])['label'], 'reserved' => true, 'holders' => ($entries[$ability] ?? ['holders' => []])['holders']];
            }
        }

        return [...$entries, ...$this->defined];
    }

    /**
     * A name an ability can have: not the wildcard, not empty, and nothing a list of them would split.
     *
     * Anchored with `\z`, because `$` also matches before a line feed at the end, and a name read from
     * the configuration or handed to define() can carry one.
     */
    private function isName(string $ability): bool
    {
        return $ability !== self::WILDCARD && preg_match('/^[^\s,*]+\z/u', $ability) === 1;
    }
}
