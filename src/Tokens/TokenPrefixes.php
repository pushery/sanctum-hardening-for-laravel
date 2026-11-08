<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * The first characters of each token's secret, kept so a person tells their tokens apart.
 *
 * A token is shown in plain text once, when it is minted. Afterwards its row holds a hash, and a list
 * of five tokens named "CI" says nothing about which one the build server holds. The first twelve
 * characters of the secret do, as they stand at the start of the value the build server was given,
 * and leave too much of the secret unknown to guess the rest. Where Sanctum puts a constant in front
 * of every secret, `sanctum.token_prefix`, the prefix keeps that constant and takes its twelve
 * characters after it, so it still reads like the start of the token and still tells tokens apart.
 *
 * They live in a table of the package's own, keyed by the token, rather than in a column of
 * `personal_access_tokens`: the application publishes Sanctum's migration with the day it ran
 * `install:api`, so a migration of this package that altered that table could run before it exists.
 */
final readonly class TokenPrefixes
{
    public const string TABLE = 'sanctum_hardening_token_prefixes';

    public const int LENGTH = 12;

    /** The longest part of Sanctum's constant a prefix keeps, so the prefix fits its column of 32. */
    public const int CONSTANT = 20;

    /** Keep the prefix of a token that was just minted. */
    public function record(NewAccessToken $token): void
    {
        $secret = explode('|', $token->plainTextToken, 2)[1] ?? '';
        $configured = Container::getInstance()->make(Repository::class)->get('sanctum.token_prefix');
        $constant = is_string($configured) && $configured !== '' && str_starts_with($secret, $configured) ? $configured : '';

        DB::table(self::TABLE)->insert([
            'token_id' => self::key($token->accessToken->getKey()),
            'display_prefix' => mb_substr($constant, 0, self::CONSTANT).mb_substr(substr($secret, strlen($constant)), 0, self::LENGTH),
            'created_at' => Date::now(),
        ]);
    }

    /**
     * The prefixes of the given tokens, by token key. A token minted outside the package has none.
     *
     * @param  iterable<Model>  $tokens
     * @return array<array-key, string>
     */
    public function of(iterable $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            $keys[] = self::key($token->getKey());
        }

        return array_filter(
            DB::table(self::TABLE)->whereIn('token_id', $keys)->pluck('display_prefix', 'token_id')->all(),
            is_string(...),
        );
    }

    /**
     * Forget the prefixes of tokens that no longer exist, and say how many.
     *
     * A token deleted by a query, the way `sanctum:prune-expired` and `$user->tokens()->delete()` delete
     * them, fires no model event, so its prefix outlives it. The tokens are looked up through the token
     * model by key rather than joined, because a prefix's key is a string and a token's key is a number
     * on most schemas.
     */
    public function prune(int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->select('token_id')->lazyById($size, 'token_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));
            $existing = array_map(self::key(...), $model->newQuery()->whereIn($model->getKeyName(), $keys)->pluck($model->getKeyName())->all());
            $gone = array_values(array_diff($keys, $existing));

            if ($gone !== []) {
                $pruned += DB::table(self::TABLE)->whereIn('token_id', $gone)->delete();
            }
        }

        return $pruned;
    }

    /** Forget the prefix of a token that was deleted. */
    public function forget(mixed $tokenKey): void
    {
        DB::table(self::TABLE)->where('token_id', self::key($tokenKey))->delete();
    }

    /** A token's or a user's key as the string it is stored under. */
    public static function key(mixed $key): string
    {
        return is_scalar($key) ? (string) $key : '';
    }
}
