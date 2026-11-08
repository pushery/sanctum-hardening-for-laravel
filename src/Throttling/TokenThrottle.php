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
 * in for is counted by its address, kept as a keyed hash: a rate limiter's key is a cache key, and
 * error trackers record cache keys.
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
            : 'address:'.hash_hmac('sha256', (string) $request->ip(), $this->secret());

        return [
            Limit::perMinute($this->positive('per_user', 120))->by('sanctum-hardening:'.$holder)->response($this->tooMany(...)),
            // A session or a transient token has no row: its requests count as one token of the holder.
            Limit::perMinute($this->positive('per_token', 60))
                ->by('sanctum-hardening:'.($token instanceof Model ? 'token:'.TokenPrefixes::key($token->getKey()) : 'session:'.$holder))
                ->response($this->tooMany(...)),
        ];
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
