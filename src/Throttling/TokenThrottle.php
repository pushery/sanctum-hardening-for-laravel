<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pushery\HttpApi\Errors\ErrorRenderer;
use Pushery\HttpApi\HttpApiServiceProvider;
use Pushery\SanctumHardening\Tokens\Tokens;

/**
 * The limits of the named limiter the API runs behind, from the tier of the request's holder, in two
 * stages: each token on its own, and the holder over all of its tokens. Without the second, a holder
 * gets past every limit of a token by minting another one. The tier comes from the application where it
 * binds a ResolvesThrottleTier, and from `throttle.*` everywhere else.
 *
 * A request over a limit answers `429` as a problem document (RFC 9457) with `Retry-After`, whatever
 * the request asked for, because only an API client meets this limiter. Where pushery/http-api-for-laravel
 * answers the errors of the request, the answer is that package's error `rate_limited` with the same
 * headers, like every other error of the API. A write the tier allows none of stays a problem document
 * either way: `rate_limited` names a wait, and waiting lets no write through. A request nobody is signed
 * in for is counted by its address, an IPv6 address by its /64, kept as a keyed hash: a rate
 * limiter's key is a cache key, and error trackers record cache keys.
 */
final readonly class TokenThrottle
{
    public function __construct(
        private Repository $config,
        private Translator $translator,
    ) {}

    /**
     * @return list<Limit>
     *
     * @throws HttpResponseException for a write the tier allows none of, answered before anything is counted
     */
    public function limits(Request $request): array
    {
        $user = $request->user();
        // @phpstan-ignore function.alreadyNarrowedType (only the analyzed user model is known to use HasApiTokens; a host's model need not)
        $token = $user instanceof Model && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $holder = $user instanceof Model
            ? 'user:'.$user->getMorphClass().':'.Tokens::key($user->getKey())
            : 'address:'.hash_hmac('sha256', $this->counted((string) $request->ip()), $this->secret());
        $tier = $this->tierFor($user instanceof Model ? $user : null);
        $writes = ! $request->isMethodSafe();
        // Where pushery/http-api-for-laravel answers the errors of the request, the limits carry no answer of their own,
        // so Laravel's throttle throws its exception with the rate limit headers and that package answers it.
        $itsOwn = ! $this->answeredByTheApiPackage($request);
        $seconds = [];
        $budgets = [];
        $minutes = [];

        foreach ([
            [$tier->perHolder, $holder],
            // A session or a transient token has no row: its requests count as one token of the holder.
            [$tier->perToken, $token instanceof Model ? 'token:'.Tokens::key($token->getKey()) : 'session:'.$holder],
        ] as [$counter, $by]) {
            if (! $counter instanceof ThrottleCounter) {
                continue;
            }

            if ($counter->perSecond !== null) {
                $seconds[] = $this->answering(Limit::perSecond($counter->perSecond)->by('sanctum-hardening:second:'.$by), $itsOwn);
            }

            if ($writes && $counter->writesPerMinute !== null) {
                // Laravel's throttle lets the first request through a limit of none, so the refusal comes from here.
                if ($counter->writesPerMinute === 0) {
                    throw new HttpResponseException($this->writeRefused($request));
                }

                $budgets[] = $this->answering(Limit::perMinute($counter->writesPerMinute)->by('sanctum-hardening:writes:'.$by), $itsOwn);
            }

            $minutes[] = $this->answering($this->limit($counter->perMinute, $counter->burst)->by('sanctum-hardening:'.$by), $itsOwn);
        }

        // Laravel's throttle asks every limit before it counts a request in any. Its Redis variant counts each limit as
        // it asks it, so the limits per second and the budgets of writes come first: a request one of them refuses has
        // then cost nothing against the rate. The token's rate comes last, so the rate limit headers name its numbers.
        return [...$seconds, ...$budgets, ...$minutes];
    }

    /**
     * The tier the holder's requests count against: the application's where its ResolvesThrottleTier gives one, and the
     * one `throttle.*` configures for every other holder and for a request nobody is signed in for. The resolver is asked
     * on every call, so a tier the application changes counts at once.
     */
    public function tierFor(?Model $holder): ThrottleTier
    {
        $tier = $holder instanceof Model ? Container::getInstance()->make(ResolvesThrottleTier::class)->tierFor($holder) : null;

        return $tier ?? $this->configuredTier();
    }

    /**
     * The tier `throttle.*` configures: `per_token` and `burst_per_token` for each token, `per_user` and `burst_per_user`
     * for its holder over all of its tokens, with no limit per second and no budget of writes.
     */
    public function configuredTier(): ThrottleTier
    {
        return new ThrottleTier(
            perToken: new ThrottleCounter($this->perToken(), burst: $this->burst('burst_per_token')),
            perHolder: new ThrottleCounter($this->perUser(), burst: $this->burst('burst_per_user')),
        );
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
    private function limit(int $perMinute, ?int $burst): Limit
    {
        if ($burst === null || $burst <= $perMinute) {
            return Limit::perMinute($perMinute);
        }

        return new Limit('', $burst, (int) round($burst / $perMinute * 60));
    }

    /**
     * The limit with the limiter's own answer to a request over it. Without one, Laravel's throttle throws its
     * ThrottleRequestsException with the rate limit headers, which pushery/http-api-for-laravel answers.
     */
    private function answering(Limit $limit, bool $itsOwn): Limit
    {
        return $itsOwn ? $limit->response($this->tooMany(...)) : $limit;
    }

    /**
     * Whether pushery/http-api-for-laravel answers the errors of this request, as it does from 0.4.0 on for a request of
     * the API. Its provider, once loaded, hands its renderer to Laravel's exception handler, which asks the renderer
     * under the two switches read here. The package is no requirement: where it is not installed, where its provider
     * is not loaded, or where a switch turns its errors off, the limiter answers itself.
     */
    private function answeredByTheApiPackage(Request $request): bool
    {
        $application = Container::getInstance();

        if (! class_exists(HttpApiServiceProvider::class) || ! method_exists($application, 'providerIsLoaded') || $application->providerIsLoaded(HttpApiServiceProvider::class) !== true) {
            return false;
        }

        if ($this->config->get('http-api.enabled') === false || $this->config->get('http-api.errors.enabled') === false) {
            return false;
        }

        return $application->make(ErrorRenderer::class)->answersWithErrors($request);
    }

    /**
     * The answer to a request over a limit.
     *
     * @param  array<array-key, mixed>  $headers  the rate limit headers Laravel's throttle computed
     */
    private function tooMany(Request $request, array $headers): JsonResponse
    {
        $detail = $this->translator->get('sanctum-hardening::messages.throttled');

        return $this->problem($request, is_string($detail) ? $detail : '', $headers);
    }

    /**
     * The answer to a write the tier allows none of. Waiting lets no write through, so it names no `Retry-After`.
     */
    private function writeRefused(Request $request): JsonResponse
    {
        $detail = $this->translator->get('sanctum-hardening::messages.writes_refused');

        return $this->problem($request, is_string($detail) ? $detail : '', []);
    }

    /**
     * A problem document (RFC 9457) with the status 429.
     *
     * @param  array<array-key, mixed>  $headers  the headers it is sent with besides its content type
     */
    private function problem(Request $request, string $detail, array $headers): JsonResponse
    {
        return new JsonResponse(
            // `instance` names the occurrence, RFC 9457 §3.1.5: the path the request was refused at.
            ['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429, 'detail' => $detail, 'instance' => '/'.ltrim($request->path(), '/')],
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

    /** A burst of `throttle.*`, or none where it is no whole number of one or more. */
    private function burst(string $key): ?int
    {
        $value = $this->config->get('sanctum-hardening.throttle.'.$key);

        return is_int($value) && $value >= 1 ? $value : null;
    }

    private function positive(string $key, int $shipped): int
    {
        $value = $this->config->get('sanctum-hardening.throttle.'.$key);

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }
}
