<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
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
 * application trusts, never a header the client sets. A token is limited to at most
 * `minting.max_networks_per_token` entries, 50 unless the application sets another number.
 */
final readonly class TokenNetworks
{
    public const string TABLE = 'sanctum_hardening_token_networks';

    /** The most addresses and networks a token may be limited to where `minting.max_networks_per_token` names none. */
    private const int MOST = 50;

    /**
     * Limit a token to these addresses and networks, or lift the limit with an empty list.
     *
     * The old list goes and the new one comes in one transaction. A token without a row is accepted from
     * any address, so a write that failed between the two would otherwise leave a token without any limit.
     *
     * @param  array<array-key, mixed>  $networks
     *
     * @throws MintRefused where one of them is no address and no network, or where they are more than a token may be
     *                     limited to
     */
    public function restrict(Model $token, array $networks): void
    {
        $checked = self::checked($networks);
        $key = TokenPrefixes::key($token->getKey());
        $stored = $checked === [] ? null : json_encode($checked, JSON_THROW_ON_ERROR);

        DB::transaction(static function () use ($key, $stored): void {
            DB::table(self::TABLE)->where('token_id', $key)->delete();

            if ($stored !== null) {
                DB::table(self::TABLE)->insert(['token_id' => $key, 'networks' => $stored]);
            }
        });
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

    /**
     * Forget the networks of a token that was deleted.
     *
     * @param  mixed  $tokenKey  the key of the token as its model holds it, an integer or a string by the type of the key
     */
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
     * @param  mixed  $stored  the JSON a row keeps, as the connection returns it, which differs between the engines
     * @return list<string>
     */
    private function decoded(mixed $stored): array
    {
        $networks = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($networks) ? array_values(array_filter($networks, is_string(...))) : [];
    }

    /**
     * The entries as they are kept: each trimmed and checked, every one once, and no more of them than a token
     * may be limited to.
     *
     * @param  array<array-key, mixed>  $networks
     * @return list<string>
     *
     * @throws MintRefused where one of them is no address and no network, or where they are more than a token
     *                     may be limited to
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

        $checked = array_values(array_unique($checked));
        $most = self::most();

        // admits() reads the whole list and checks the address against each entry on every request with the
        // token, so the length of the list is what each of those requests costs.
        if (count($checked) > $most) {
            throw MintRefused::tooManyNetworks($most);
        }

        return $checked;
    }

    /**
     * The most addresses and networks a token may be limited to: `minting.max_networks_per_token`, or MOST where
     * the setting names no whole number of one or more.
     */
    private static function most(): int
    {
        $configured = Container::getInstance()->make(Repository::class)->get('sanctum-hardening.minting.max_networks_per_token');

        return is_int($configured) && $configured >= 1 ? $configured : self::MOST;
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
