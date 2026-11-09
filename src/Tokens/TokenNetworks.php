<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The addresses and networks a token is limited to.
 *
 * A token without an entry is accepted from any address. One with entries is accepted only from an
 * address inside one of them: an IPv4 or IPv6 address, or a network in CIDR notation. A request from
 * anywhere else is refused the way a token that does not exist is refused, so the answer does not say
 * that the token is valid. The address is the request's as Laravel reads it, through the proxies the
 * application trusts, never a header the client sets.
 */
final readonly class TokenNetworks
{
    public const string TABLE = 'sanctum_hardening_token_networks';

    /**
     * Limit a token to these addresses and networks, or lift the limit with an empty list.
     *
     * @param  array<array-key, mixed>  $networks
     *
     * @throws MintRefused where one of them is no address and no network
     */
    public function restrict(Model $token, array $networks): void
    {
        $checked = self::checked($networks);
        $key = TokenPrefixes::key($token->getKey());

        DB::table(self::TABLE)->where('token_id', $key)->delete();

        if ($checked !== []) {
            DB::table(self::TABLE)->insert(['token_id' => $key, 'networks' => json_encode($checked, JSON_THROW_ON_ERROR)]);
        }
    }

    /**
     * The addresses and networks a token is limited to, none where it is not limited.
     *
     * @return list<string>
     */
    public function of(Model $token): array
    {
        return $this->decoded(DB::table(self::TABLE)->where('token_id', TokenPrefixes::key($token->getKey()))->value('networks'));
    }

    /**
     * The addresses and networks of each of the given tokens that is limited, by token key, in one query.
     *
     * @param  iterable<Model>  $tokens
     * @return array<string, list<string>>
     */
    public function ofEach(iterable $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            $keys[] = TokenPrefixes::key($token->getKey());
        }

        $limits = [];

        foreach (DB::table(self::TABLE)->whereIn('token_id', $keys)->get(['token_id', 'networks']) as $row) {
            $limits[TokenPrefixes::key($row->token_id ?? null)] = $this->decoded($row->networks ?? null);
        }

        return $limits;
    }

    /** Whether a request from `$address` may use the token: always where it is not limited. */
    public function admits(Model $token, ?string $address): bool
    {
        $networks = $this->of($token);

        return $networks === [] || ($address !== null && IpUtils::checkIp($address, $networks));
    }

    /** Forget the networks of a token that was deleted. */
    public function forget(mixed $tokenKey): void
    {
        DB::table(self::TABLE)->where('token_id', TokenPrefixes::key($tokenKey))->delete();
    }

    /**
     * Forget the networks of tokens that no longer exist, and say how many, the way TokenPrefixes::prune()
     * forgets their prefixes.
     */
    public function prune(int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->select('token_id')->lazyById($size, 'token_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));
            $existing = array_map(TokenPrefixes::key(...), $model->newQuery()->whereIn($model->getKeyName(), $keys)->pluck($model->getKeyName())->all());
            $gone = array_values(array_diff($keys, $existing));

            if ($gone !== []) {
                $pruned += DB::table(self::TABLE)->whereIn('token_id', $gone)->delete();
            }
        }

        return $pruned;
    }

    /**
     * The list a row keeps as JSON, the entries that are text.
     *
     * @return list<string>
     */
    private function decoded(mixed $stored): array
    {
        $networks = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($networks) ? array_values(array_filter($networks, is_string(...))) : [];
    }

    /**
     * The entries as they are kept: each trimmed and checked, every one once.
     *
     * @param  array<array-key, mixed>  $networks
     * @return list<string>
     *
     * @throws MintRefused where one of them is no address and no network
     */
    public static function checked(array $networks): array
    {
        $checked = [];

        foreach ($networks as $network) {
            $entry = is_string($network) ? trim($network) : '';

            if (! self::isNetwork($entry)) {
                throw MintRefused::network(is_scalar($network) ? (string) $network : get_debug_type($network));
            }

            $checked[] = $entry;
        }

        return array_values(array_unique($checked));
    }

    /**
     * Check every token Sanctum authenticates against its networks, through Sanctum's authentication
     * callback. A callback the application gave Sanctum before keeps answering, and a token passes only
     * where both let it.
     *
     * Called once the application has booted, so a callback set in its own boot() is the one kept. A
     * second call, from an application booted again in the same process, keeps the application's
     * callback rather than the package's own from before.
     */
    public static function guardSanctum(): void
    {
        $current = Sanctum::$accessTokenAuthenticationCallback;

        if (! $current instanceof NetworksCallback) {
            NetworksCallback::$application = $current;
        }

        Sanctum::authenticateAccessTokensUsing(new NetworksCallback);
    }

    private static function isNetwork(string $entry): bool
    {
        [$address, $length] = array_pad(explode('/', $entry, 2), 2, null);
        $v4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $v6 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        return ($v4 || $v6) && ($length === null || (ctype_digit($length) && (int) $length <= ($v4 ? 32 : 128)));
    }
}
