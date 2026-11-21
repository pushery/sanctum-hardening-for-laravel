<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
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
 * The confirmation a mint asks for is the one Laravel's `password.confirm` records in the session.
 * confirmPassword() records it as well, after checking the password, so a screen on a host without
 * that route, the console of a workspace on its own subdomain for instance, can ask for it itself.
 *
 * The application's own code mints through mintForSystem(): a reserved ability is allowed there, and
 * no limit of the self-service applies. A token that carries a reserved ability stays out of the
 * self-service's list and cannot be revoked there.
 *
 * A token revoked here is not deleted: TokenRevocations expires it and records who revoked it and
 * when, and it leaves the list.
 *
 * Every mint takes the addresses and networks the token is limited to, none by default; see
 * TokenNetworks.
 */
final readonly class PersonalTokens
{
    /** The longest name a token may have, the length the self-service screen allows. */
    public const int LONGEST_NAME = 255;

    /** The wrong passwords a user may type into confirmPassword() a minute, from every address and session together. */
    public const int CONFIRM_ATTEMPTS = 5;

    /** The seconds a confirmation stays good for the one mint it covers, where `minting.password_timeout` is 0. */
    public const int ONE_MINT_WINDOW = 300;

    public function __construct(
        private AbilityCatalog $catalog,
        private Repository $config,
        private Gate $gate,
        private RateLimiter $limiter,
        private ResolvesTokenLimit $limits,
        private TokenPrefixes $prefixes,
        private TokenRevocations $revocations,
        private TokenNetworks $networks,
        private Hasher $hasher,
        private TokenRotations $rotations = new TokenRotations,
    ) {}

    /**
     * Mint a token for the signed-in user of the request.
     *
     * @param  array<array-key, mixed>  $abilities
     * @param  int|null  $days  one of the configured lifetimes, 0 for none where that is allowed, null for the default
     * @param  array<array-key, mixed>  $networks  the addresses and networks the token is limited to, none for no limit
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mint(Request $request, string $name, array $abilities, ?int $days = null, array $networks = []): NewAccessToken
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw MintRefused::notTokenable();
        }

        return $this->mintFor($request, $user, $name, $abilities, $days, $networks);
    }

    /**
     * Mint a token that belongs to `$tokenable` under the rules of the self-service, counted for that
     * tokenable: the signed-in user, or a model whose tokens the application's Gate lets that user
     * manage. The password is the signed-in user's.
     *
     * @param  array<array-key, mixed>  $abilities
     * @param  int|null  $days  one of the configured lifetimes, 0 for none where that is allowed, null for the default
     * @param  array<array-key, mixed>  $networks  the addresses and networks the token is limited to, none for no limit
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mintFor(Request $request, Model $tokenable, string $name, array $abilities, ?int $days = null, array $networks = []): NewAccessToken
    {
        return $this->mintChecked($request, $tokenable, $name, $abilities, $days, $networks, rotating: false);
    }

    /**
     * Rotate one token of the list listFor() gives for the same request and tokenable: mint its successor with the
     * old token's name, abilities, networks and lifetime, and end the old one after the overlap, so whatever still
     * calls with the old key has that long to take the new one. The successor, or null where the token is not in that
     * list, has expired, or was replaced already.
     *
     * A rotation is a mint and asks what a mint asks: the confirmed password, the pace, and abilities and networks the
     * token may still be given. It is not refused at the limit of tokens: for the overlap the old token and its
     * successor are one token's two keys, and a holder at the limit is the one who most needs to rotate.
     *
     * @param  int|null  $overlapHours  how long the old token keeps holding, `rotation.overlap_hours` where null
     *
     * @throws AbilityRefused|MintRefused
     */
    public function rotate(Request $request, int|string $tokenId, ?Model $tokenable = null, ?int $overlapHours = null): ?NewAccessToken
    {
        $owner = $tokenable ?? $request->user();
        $token = $this->listFor($request, $tokenable)->first(static fn (Model $token): bool => TokenPrefixes::key($token->getKey()) === (string) $tokenId);

        if (! $owner instanceof Model || ! $token instanceof Model || Tokens::expired($token) || $this->rotations->endsOf([$token]) !== []) {
            return null;
        }

        $name = $token->getAttribute('name');
        $successor = $this->mintChecked(
            $request,
            $owner,
            is_string($name) ? $name : '',
            Tokens::abilitiesOf($token),
            $this->lifetimeOf($token),
            $this->networks->of($token),
            rotating: true,
        );

        $by = $request->user();
        $hours = max(1, $overlapHours ?? $this->defaultOverlap());
        $this->rotations->record($token, $successor->accessToken, Date::now()->addHours($hours), $by instanceof Model ? $by : null);

        return $successor;
    }

    /**
     * The overlap of a rotation that names none, in hours: `rotation.overlap_hours`.
     */
    public function defaultOverlap(): int
    {
        return $this->positive('rotation.overlap_hours', 24);
    }

    /**
     * The overlaps a rotation on the screen may be given, in hours, the shortest first: the ones in `rotation.overlaps`
     * and the default one. Empty where the list names none, and the screen then offers no choice.
     *
     * @return list<int>
     */
    public function overlaps(): array
    {
        $configured = $this->config->get('sanctum-hardening.rotation.overlaps', []);
        $overlaps = array_filter(array_filter(is_array($configured) ? $configured : [], is_int(...)), static fn (int $hours): bool => $hours >= 1);

        if ($overlaps === []) {
            return [];
        }

        $overlaps[] = $this->defaultOverlap();
        $overlaps = array_values(array_unique($overlaps));
        sort($overlaps);

        return $overlaps;
    }

    /**
     * When each of these tokens that was replaced by a rotation stops holding, by token key.
     *
     * @param  iterable<Model>  $tokens
     * @return array<string, DateTimeImmutable>
     */
    public function replacedUntil(iterable $tokens): array
    {
        return $this->rotations->endsOf($tokens);
    }

    /**
     * The mint of mintFor(), and of rotate() where `$rotating`, which does not count the holder's tokens against the
     * limit.
     *
     * @param  array<array-key, mixed>  $abilities
     * @param  array<array-key, mixed>  $networks
     *
     * @throws AbilityRefused|MintRefused
     */
    private function mintChecked(Request $request, Model $tokenable, string $name, array $abilities, ?int $days, array $networks, bool $rotating): NewAccessToken
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

        $granted = $this->catalog->check($abilities, holder: $tokenable);
        $limitedTo = TokenNetworks::checked($networks);
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

            if (! $rotating && $this->tokensOf($tokenable, true)->count() >= $limit) {
                throw MintRefused::limitReached($limit);
            }

            // Where each mint asks for the password, this one spends the confirmation it was let through on. The mark
            // is kept in the cache rather than the session: another request of the session, sent at the same moment,
            // carries a copy of the session read before this one wrote anything.
            if ($this->confirmsEachMint() && ! Cache::add($this->spent($request, $this->confirmedAt($request) ?? 0), true, self::ONE_MINT_WINDOW)) {
                throw MintRefused::passwordNotConfirmed();
            }

            $created = $tokenable->createToken($name, $granted, $expiresAt);
            $minted = $created instanceof NewAccessToken ? $created : throw new LogicException('createToken() answered something other than a NewAccessToken.');

            // Only a token that was minted counts against the minute, so a refused request costs nothing.
            $this->limiter->hit($holder, 60);
            $this->prefixes->record($minted);
            $this->networks->restrict($minted->accessToken, $limitedTo);

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
     * @param  array<array-key, mixed>  $networks  the addresses and networks the token is limited to, none for no limit
     *
     * @throws AbilityRefused|MintRefused
     */
    public function mintForSystem(Model $user, string $name, array $abilities, ?DateTimeInterface $expiresAt = null, array $networks = []): NewAccessToken
    {
        if (! method_exists($user, 'createToken')) {
            throw MintRefused::notTokenable();
        }

        $this->refuseUnnamed($name);

        $granted = $this->catalog->check($abilities, system: true);
        $limitedTo = TokenNetworks::checked($networks);
        $created = $user->createToken($name, $granted, $expiresAt);
        $minted = $created instanceof NewAccessToken ? $created : throw new LogicException('createToken() answered something other than a NewAccessToken.');
        $this->prefixes->record($minted);
        $this->networks->restrict($minted->accessToken, $limitedTo);

        return $minted;
    }

    /**
     * The tokens the self-service shows: those of the signed-in user of the request, or of `$tokenable`
     * where the Gate lets that user manage them. Every one without a reserved ability that was not
     * revoked, the newest first, an expired one included until it is pruned. A guest, or a user the
     * Gate does not allow, sees none.
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
     * a reserved ability, as the signed-in user of the request; whether one was revoked. A token that
     * another request revoked after this one read the list, from a second screen for instance, keeps
     * that revocation, and the answer is false.
     */
    public function revoke(Request $request, int|string $tokenId, ?Model $tokenable = null): bool
    {
        $token = $this->listFor($request, $tokenable)->first(static fn (Model $token): bool => TokenPrefixes::key($token->getKey()) === (string) $tokenId);

        if (! $token instanceof Model) {
            return false;
        }

        $by = $request->user();

        return $this->revocations->revoke($token, $by instanceof Model ? $by : null);
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
     * The tokens of a tokenable the self-service sees, without the revoked ones, and the ones that have
     * not expired alone where `$live`.
     *
     * @return Collection<int, Model>
     */
    private function tokensOf(Model $tokenable, bool $live): Collection
    {
        $model = Tokens::model();

        // A tokenable holds a handful of tokens, so the expired and the revoked ones are told apart here
        // rather than in SQL.
        $tokens = $model->newQuery()
            ->where($model->qualifyColumn('tokenable_type'), $tokenable->getMorphClass())
            ->where($model->qualifyColumn('tokenable_id'), $tokenable->getKey())
            ->orderByDesc($model->getKeyName())
            ->get();
        $revoked = $this->revocations->among($tokens);

        return $tokens
            ->reject(fn (Model $token): bool => in_array(TokenPrefixes::key($token->getKey()), $revoked, true) || $this->catalog->holdsReserved(Tokens::abilitiesOf($token)) || ($live && Tokens::expired($token)))
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
     * `password.confirm` middleware records it. Where `minting.password_timeout` is 0, which asks for
     * the password before every mint, a confirmation covers the one mint after it: it stays good for
     * ONE_MINT_WINDOW seconds, until a mint spends it.
     *
     * Where `minting.password_timeout` is null, Laravel's `auth.password_timeout` counts, read the way
     * `password.confirm` reads it: 0, an empty value and none at all mean three hours.
     */
    public function passwordConfirmed(Request $request): bool
    {
        $confirmedAt = $this->confirmedAt($request);

        if ($confirmedAt !== null && $this->confirmsEachMint()) {
            return Date::now()->getTimestamp() - $confirmedAt <= self::ONE_MINT_WINDOW && ! Cache::has($this->spent($request, $confirmedAt));
        }

        $timeout = $this->config->get('sanctum-hardening.minting.password_timeout') ?? ($this->config->get('auth.password_timeout') ?: 10_800);

        return $confirmedAt !== null && is_numeric($timeout) && Date::now()->getTimestamp() - $confirmedAt <= (int) $timeout;
    }

    /** The moment the session recorded the password confirmation at, or null where it holds none it can read. */
    private function confirmedAt(Request $request): ?int
    {
        $confirmedAt = $request->hasSession() ? $request->session()->get('auth.password_confirmed_at') : null;

        return is_numeric($confirmedAt) ? (int) $confirmedAt : null;
    }

    /** Whether `minting.password_timeout` is 0, which asks for the password before every mint. */
    private function confirmsEachMint(): bool
    {
        $timeout = $this->config->get('sanctum-hardening.minting.password_timeout');

        return is_numeric($timeout) && (int) $timeout === 0;
    }

    /**
     * The cache key that marks the confirmation the session recorded at `$confirmedAt` as spent by a mint
     * of the signed-in user. It names the user and the moment, nothing a person typed.
     */
    private function spent(Request $request, int $confirmedAt): string
    {
        $user = $request->user();
        $holder = $user instanceof Model ? $user->getMorphClass().':'.TokenPrefixes::key($user->getKey()) : '';

        return 'sanctum-hardening:confirmation-spent:'.$holder.':'.$confirmedAt;
    }

    /**
     * Check the password of the signed-in user of the request against the one the user's model holds,
     * as the Eloquent user provider checks it, and record the confirmation in the request's session the
     * way `password.confirm` does; whether it was the password. A request without a signed-in user or
     * without a session confirms nothing. CONFIRM_ATTEMPTS wrong passwords a minute for the user, from
     * every address and session together, and the next attempt is refused before the password is read.
     * A session is bound to no address, so whoever holds one sends from as many addresses as they have,
     * and a count for each address would give them CONFIRM_ATTEMPTS for every one. Only a signed-in
     * user reaches the count, so nobody without a session of the user can use it up.
     *
     * Every attempt is counted before the password is read, and the right password clears the count.
     * Requests of one session run at once, and a count read before a slow hash and raised after it let
     * each request that arrived during the hash find the count of before and check one more password.
     *
     * @throws MintRefused too_fast once the attempts of the minute are used up
     */
    public function confirmPassword(Request $request, string $password): bool
    {
        $user = $request->user();

        if (! $user instanceof Model || ! $request->hasSession()) {
            return false;
        }

        $attempts = 'sanctum-hardening:confirm:'.$user->getMorphClass().':'.TokenPrefixes::key($user->getKey());

        // The count the increment answers is this attempt's own, however many arrive at once.
        if ($this->limiter->hit($attempts, 60) > self::CONFIRM_ATTEMPTS) {
            throw MintRefused::tooFast($this->limiter->availableIn($attempts));
        }

        if (! $this->hasher->check($password, $user->getAuthPassword())) {
            return false;
        }

        $this->limiter->clear($attempts);
        $request->session()->put('auth.password_confirmed_at', Date::now()->getTimestamp());

        return true;
    }

    /**
     * The lifetime the successor of a token is given: the old token's, in whole days, where it is one a token may be
     * given; none where the old one had none and that is allowed; the default lifetime otherwise.
     */
    private function lifetimeOf(Model $token): ?int
    {
        $expires = $token->getAttribute('expires_at');

        if (! $expires instanceof DateTimeInterface) {
            return $this->config->get('sanctum-hardening.minting.allow_no_expiry') === true ? 0 : null;
        }

        $created = $token->getAttribute('created_at');
        $days = $created instanceof DateTimeInterface ? (int) round(($expires->getTimestamp() - $created->getTimestamp()) / 86400) : 0;

        return in_array($days, $this->lifetimes(), true) ? $days : null;
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

        return array_values(array_filter(array_filter(is_array($configured) ? $configured : [], is_int(...)), static fn (int $days): bool => $days >= 1 && $days <= $longest));
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
