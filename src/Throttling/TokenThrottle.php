<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;

/**
 * The limits of the named limiter the API runs behind, in two stages: each token on its own, and each
 * user over all of their tokens. Without the second, a user gets past every limit of a token by
 * minting another one.
 *
 * A request over either answers `429` as a problem document (RFC 9457) with `Retry-After`, whatever
 * the request asked for, because only an API client meets this limiter. A request nobody is signed
 * in for is counted by its address, an IPv6 address by its /64, kept as a keyed hash: a rate
 * limiter's key is a cache key, and error trackers record cache keys.
 */
final readonly class TokenThrottle
{
    public function __construct(
        private Repository $config,
        private Translator $translator,
    ) {}

    /** @return list<Limit> */
    public function limits(Request $request): array
    {
        $user = $request->user();
        // @phpstan-ignore function.alreadyNarrowedType (only the analyzed user model is known to use HasApiTokens; a host's model need not)
        $token = $user instanceof Model && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $holder = $user instanceof Model
            ? 'user:'.$user->getMorphClass().':'.TokenPrefixes::key($user->getKey())
            : 'address:'.hash_hmac('sha256', $this->counted((string) $request->ip()), $this->secret());

        return [
            $this->limit($this->perUser(), 'burst_per_user')->by('sanctum-hardening:'.$holder)->response($this->tooMany(...)),
            // A session or a transient token has no row: its requests count as one token of the holder.
            $this->limit($this->perToken(), 'burst_per_token')
                ->by('sanctum-hardening:'.($token instanceof Model ? 'token:'.TokenPrefixes::key($token->getKey()) : 'session:'.$holder))
                ->response($this->tooMany(...)),
        ];
    }

    /**
     * The requests a minute each token makes on average: `throttle.per_token`, or 60 where it is no whole number of one
     * or more.
     */
    public function perToken(): int
    {
        return $this->positive('per_token', 60);
    }

    /**
     * The requests a minute each user makes over all of their tokens on average: `throttle.per_user`, or 120 where it
     * is no whole number of one or more.
     */
    public function perUser(): int
    {
        return $this->positive('per_user', 120);
    }

    /**
     * A limit of `$perMinute` requests a minute on average that lets a quiet holder send up to its burst at once: one
     * window of `burst` requests, as long as `burst / rate` minutes take, to the second. With no burst above the rate,
     * the window is the minute, as it always was.
     */
    private function limit(int $perMinute, string $burst): Limit
    {
        $allowed = $this->config->get('sanctum-hardening.throttle.'.$burst);

        if (! is_int($allowed) || $allowed <= $perMinute) {
            return Limit::perMinute($perMinute);
        }

        return new Limit('', $allowed, (int) round($allowed / $perMinute * 60));
    }

    /**
     * The answer to a request over a limit.
     *
     * @param  array<array-key, mixed>  $headers  the rate limit headers Laravel's throttle computed
     */
    private function tooMany(Request $request, array $headers): JsonResponse
    {
        $detail = $this->translator->get('sanctum-hardening::messages.throttled');

        return new JsonResponse(
            // `instance` names the occurrence, RFC 9457 §3.1.5: the path the request was refused at.
            ['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429, 'detail' => is_string($detail) ? $detail : '', 'instance' => '/'.ltrim($request->path(), '/')],
            429,
            [...$headers, 'Content-Type' => 'application/problem+json'],
        );
    }

    /**
     * What a request nobody is signed in for is counted by. An IPv6 address counts by its /64, the
     * network a single line or host is given, so a client cannot draw a fresh count from each of its
     * addresses. An IPv4 address counts as it is, and so does one mapped into IPv6, which would
     * otherwise share the one /64 of every mapped address. Anything else counts as it was given.
     */
    private function counted(string $address): string
    {
        $packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? false : inet_pton($address);

        if (! is_string($packed) || str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return $address;
        }

        // The first eight bytes are the /64, written out in hex; the key is hashed, so no reader sees it.
        return bin2hex(substr($packed, 0, 8)).'/64';
    }

    private function secret(): string
    {
        $key = $this->config->get('app.key');

        return is_string($key) ? $key : '';
    }

    private function positive(string $key, int $shipped): int
    {
        $value = $this->config->get('sanctum-hardening.throttle.'.$key);

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }
}
