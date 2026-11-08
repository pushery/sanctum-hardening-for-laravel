<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\NewAccessToken;
use LogicException;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Abilities\AbilityRefused;

/**
 * The personal access tokens of a user, minted, listed and revoked under the package's rules.
 *
 * The self-service mints for the signed-in user of a request and only behind a password confirmed a
 * moment ago. A user holds at most a fixed number of tokens that have not expired, mints at most a
 * few a minute, and two mints at once are never both counted against the same room: a taken-over
 * session cannot spray long-lived tokens. A token lives for one of the configured lifetimes.
 *
 * A token can also belong to another tokenable, a workspace for instance. It is minted, listed and
 * revoked under the same rules, counted for that tokenable, and only where the application's Gate
 * lets the signed-in user manage its tokens.
 *
 * The application's own code mints through mintForSystem(): a reserved ability is allowed there, and
 * no limit of the self-service applies. A token that carries a reserved ability stays out of the
 * self-service's list and cannot be revoked there.
 */
final readonly class PersonalTokens
{
    /** The longest name a token may have, the length the self-service screen allows. */
    public const int LONGEST_NAME = 255;

    public function __construct(
        private AbilityCatalog $catalog,
        private Repository $config,
        private Gate $gate,
        private RateLimiter $limiter,
        private ResolvesTokenLimit $limits,
        private TokenPrefixes $prefixes,
    ) {}

    /**
     * Mint a token for the signed-in user of the request.
     *
     * @param  array<array-key, mixed>  $abilities
     * @param  int|null  $days  one of the configured lifetimes, 0 for none where that is allowed, null for the default
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mint(Request $request, string $name, array $abilities, ?int $days = null): NewAccessToken
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw MintRefused::notTokenable();
        }

        return $this->mintFor($request, $user, $name, $abilities, $days);
    }

    /**
     * Mint a token that belongs to `$tokenable` under the rules of the self-service, counted for that
     * tokenable: the signed-in user, or a model whose tokens the application's Gate lets that user
     * manage. The password is the signed-in user's.
     *
     * @param  array<array-key, mixed>  $abilities
     * @param  int|null  $days  one of the configured lifetimes, 0 for none where that is allowed, null for the default
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mintFor(Request $request, Model $tokenable, string $name, array $abilities, ?int $days = null): NewAccessToken
    {
        if (! method_exists($tokenable, 'createToken')) {
            throw MintRefused::notTokenable();
        }

        if (! $this->manages($request, $tokenable)) {
            throw MintRefused::notAllowed($this->ability());
        }

        if (! $this->passwordConfirmed($request)) {
            throw MintRefused::passwordNotConfirmed();
        }

        $this->refuseUnnamed($name);

        $granted = $this->catalog->check($abilities);
        $expiresAt = $this->expiryFor($days);
        $holder = 'sanctum-hardening:mint:'.$tokenable->getMorphClass().':'.TokenPrefixes::key($tokenable->getKey());

        // Counting and then creating is read-then-act: without the lock, a burst of requests at once
        // could all find room under the limit before any of them writes a row.
        $lock = Cache::lock($holder.':lock', 10);

        if ($lock->get() !== true) {
            throw MintRefused::tooFast(10);
        }

        try {
            if ($this->limiter->tooManyAttempts($holder, $this->positive('minting.per_minute', 5))) {
                throw MintRefused::tooFast($this->limiter->availableIn($holder));
            }

            $limit = min($this->positive('minting.max_tokens_per_user', 20), $this->limits->limitFor($tokenable) ?? PHP_INT_MAX);

            if ($this->tokensOf($tokenable, true)->count() >= $limit) {
                throw MintRefused::limitReached($limit);
            }

            $created = $tokenable->createToken($name, $granted, $expiresAt);
            $minted = $created instanceof NewAccessToken ? $created : throw new LogicException('createToken() answered something other than a NewAccessToken.');

            // Only a token that was minted counts against the minute, so a refused request costs nothing.
            $this->limiter->hit($holder, 60);
            $this->prefixes->record($minted);

            return $minted;
        } finally {
            $lock->release();
        }
    }

    /**
     * Mint a token for the application's own code, a device token for instance: a reserved ability is
     * allowed, and neither the password nor a limit of the self-service is asked.
     *
     * @param  array<array-key, mixed>  $abilities
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mintForSystem(Model $user, string $name, array $abilities, ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        if (! method_exists($user, 'createToken')) {
            throw MintRefused::notTokenable();
        }

        $this->refuseUnnamed($name);

        $created = $user->createToken($name, $this->catalog->check($abilities, system: true), $expiresAt);
        $minted = $created instanceof NewAccessToken ? $created : throw new LogicException('createToken() answered something other than a NewAccessToken.');
        $this->prefixes->record($minted);

        return $minted;
    }

    /**
     * The tokens the self-service shows: those of the signed-in user of the request, or of `$tokenable`
     * where the Gate lets that user manage them. Every one without a reserved ability, the newest
     * first, an expired one included until it is revoked or pruned. A guest, or a user the Gate does
     * not allow, sees none.
     *
     * @return Collection<int, Model>
     */
    public function listFor(Request $request, ?Model $tokenable = null): Collection
    {
        $owner = $tokenable ?? $request->user();

        return $owner instanceof Model && $this->manages($request, $owner) ? $this->tokensOf($owner, false) : new Collection;
    }

    /**
     * Revoke one token of the list listFor() gives for the same request and tokenable, never one with
     * a reserved ability; whether one was revoked.
     */
    public function revoke(Request $request, int|string $tokenId, ?Model $tokenable = null): bool
    {
        $token = $this->listFor($request, $tokenable)->first(static fn (Model $token): bool => TokenPrefixes::key($token->getKey()) === (string) $tokenId);

        return $token instanceof Model && $token->delete() === true;
    }

    /**
     * Whether the signed-in user of the request manages the tokens of `$tokenable`: their own always,
     * another model's where the application's Gate allows the ability for it.
     */
    private function manages(Request $request, Model $tokenable): bool
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            return false;
        }

        // The tokens of the signed-in user are the rows with its morph class and key.
        if ($user->getMorphClass() === $tokenable->getMorphClass() && $user->is($tokenable)) {
            return true;
        }

        return $this->gate->forUser($user)->allows($this->ability(), [$tokenable]);
    }

    /** The Gate ability that lets the signed-in user manage the tokens of another tokenable. */
    private function ability(): string
    {
        $ability = $this->config->get('sanctum-hardening.tokenable_ability');

        return is_string($ability) && trim($ability) !== '' ? $ability : 'manage-tokens';
    }

    /**
     * The tokens of a tokenable the self-service sees, the ones that have not expired alone where `$live`.
     *
     * @return Collection<int, Model>
     */
    private function tokensOf(Model $tokenable, bool $live): Collection
    {
        $model = Tokens::model();

        // A tokenable holds a handful of tokens, so the expired ones are told apart here rather than in SQL.
        return $model->newQuery()
            ->where($model->qualifyColumn('tokenable_type'), $tokenable->getMorphClass())
            ->where($model->qualifyColumn('tokenable_id'), $tokenable->getKey())
            ->orderByDesc($model->getKeyName())
            ->get()
            ->reject(fn (Model $token): bool => $this->catalog->holdsReserved(Tokens::abilitiesOf($token)) || ($live && Tokens::expired($token)))
            ->values();
    }

    /**
     * Refuse a name nobody could tell the token apart by, or one longer than LONGEST_NAME.
     *
     * @throws MintRefused
     */
    private function refuseUnnamed(string $name): void
    {
        if (trim($name) === '' || mb_strlen($name) > self::LONGEST_NAME) {
            throw MintRefused::name(self::LONGEST_NAME);
        }
    }

    /**
     * Whether the session confirmed the password within the timeout, the way Laravel's
     * `password.confirm` middleware records it.
     */
    private function passwordConfirmed(Request $request): bool
    {
        $timeout = $this->config->get('sanctum-hardening.minting.password_timeout') ?? $this->config->get('auth.password_timeout', 10_800);
        $confirmedAt = $request->hasSession() ? $request->session()->get('auth.password_confirmed_at') : null;

        return is_numeric($confirmedAt) && is_numeric($timeout) && Date::now()->getTimestamp() - (int) $confirmedAt <= (int) $timeout;
    }

    /** The expiry of a token of `$days` days: null for none where that is allowed. */
    private function expiryFor(?int $days): ?DateTimeInterface
    {
        $days ??= $this->defaultLifetime();

        if ($days === 0) {
            return $this->config->get('sanctum-hardening.minting.allow_no_expiry') === true ? null : throw MintRefused::lifetime(0);
        }

        if (! in_array($days, $this->lifetimes(), true)) {
            throw MintRefused::lifetime($days);
        }

        return Date::now()->addDays($days);
    }

    /**
     * The lifetimes a token may be given, in days: the configured ones up to the longest allowed.
     *
     * @return list<int>
     */
    public function lifetimes(): array
    {
        $configured = $this->config->get('sanctum-hardening.minting.lifetimes', []);
        $longest = $this->positive('minting.max_lifetime', 365);

        return array_values(array_filter(is_array($configured) ? $configured : [], static fn (mixed $days): bool => is_int($days) && $days >= 1 && $days <= $longest));
    }

    /**
     * The lifetime of a token minted without a choice, in days: the configured default where a token
     * may be given it, otherwise the longest allowed lifetime below it, otherwise the shortest allowed
     * one. Where no lifetime is allowed at all, the configured default, which a mint then refuses.
     */
    public function defaultLifetime(): int
    {
        $default = $this->positive('minting.default_lifetime', 90);
        $allowed = $this->lifetimes();

        if ($allowed === [] || in_array($default, $allowed, true)) {
            return $default;
        }

        $below = array_filter($allowed, static fn (int $days): bool => $days < $default);

        return $below === [] ? min($allowed) : max($below);
    }

    /** A whole number of one or more from the settings, or the shipped value where the setting is none. */
    private function positive(string $key, int $shipped): int
    {
        $value = $this->config->get('sanctum-hardening.'.$key);

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }
}
