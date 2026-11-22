<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The public identifier inside each token's key, and the characters the key starts with, by token.
 *
 * The identifier is how a key that comes in finds its token, with one lookup before the fingerprint is
 * compared, and what a log names. The prefix, the key up to and including the identifier, is what a
 * person tells their tokens apart by on the screen and in a mail: it stands at the start of the value the
 * build server was given, and it says nothing about the secret.
 *
 * The identifier of a revoked token stays as long as the record of its revocation, which names the key by
 * its prefix after the token is pruned, and TokenRevocations::prune() deletes the two together. So an
 * identifier in that record never names a second key.
 *
 * Both live in a table of the package's own, keyed by the token, rather than in columns of
 * `personal_access_tokens`: the application publishes Sanctum's migration with the day it ran
 * `install:api`, so a migration of this package that altered that table could run before it exists.
 */
final readonly class KeyIds
{
    public const string TABLE = 'sanctum_hardening_key_ids';

    /** Keep the identifier and the prefix of a token that was just minted with the key they come from. */
    public function record(Model $token, ParsedKey $key): void
    {
        DB::table(self::TABLE)->insert([
            'key_id' => $key->id,
            'token_id' => Tokens::key($token->getKey()),
            'prefix' => $key->prefix(),
            'created_at' => Moments::bound(Date::now()),
        ]);
    }

    /** Whether an identifier belongs to a token already. */
    public function taken(string $keyId): bool
    {
        return DB::table(self::TABLE)->where('key_id', $keyId)->exists();
    }

    /** The key of the token an identifier belongs to, as it is stored, or null for an identifier no token has. */
    public function tokenOf(string $keyId): ?string
    {
        $tokenId = DB::table(self::TABLE)->where('key_id', $keyId)->value('token_id');

        return is_string($tokenId) ? $tokenId : null;
    }

    /**
     * The prefixes of the given tokens' keys, by token key. A token minted outside the package has none.
     *
     * @param  iterable<Model>  $tokens
     * @return array<array-key, string>
     */
    public function of(iterable $tokens): array
    {
        return $this->column('prefix', $tokens);
    }

    /**
     * The identifiers inside the given tokens' keys, by token key. A token minted outside the package has none.
     *
     * @param  iterable<Model>  $tokens
     * @return array<array-key, string>
     */
    public function idsOf(iterable $tokens): array
    {
        return $this->column('key_id', $tokens);
    }

    /**
     * Forget the identifiers of tokens that no longer exist, and say how many.
     *
     * A token deleted by a query, the way `sanctum:prune-expired` and `$user->tokens()->delete()` delete
     * them, fires no model event, so its identifier outlives it. The tokens are looked up through the token
     * model by key rather than joined, because an identifier's token key is a string and a token's key is a
     * number on most schemas. The identifier of a revoked token stays with the record of its revocation.
     */
    public function prune(int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->select('key_id', 'token_id')->lazyById($size, 'key_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));
            $existing = array_map(Tokens::key(...), $model->newQuery()->whereIn($model->getKeyName(), $keys)->pluck($model->getKeyName())->all());
            $gone = array_values(array_diff($keys, $existing));

            if ($gone !== []) {
                $pruned += DB::table(self::TABLE)->whereIn('token_id', $gone)->whereNotIn('token_id', $this->revoked())->delete();
            }
        }

        return $pruned;
    }

    /**
     * Forget the identifier of a token that was deleted.
     *
     * @param  mixed  $tokenKey  the key of the token as its model holds it, an integer or a string by the type of the key
     */
    public function forget(mixed $tokenKey): void
    {
        DB::table(self::TABLE)->where('token_id', Tokens::key($tokenKey))->whereNotIn('token_id', $this->revoked())->delete();
    }

    /**
     * One column of the given tokens' rows, by token key.
     *
     * @param  'prefix'|'key_id'  $column
     * @param  iterable<Model>  $tokens
     * @return array<array-key, string>
     */
    private function column(string $column, iterable $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            $keys[] = Tokens::key($token->getKey());
        }

        return array_filter(DB::table(self::TABLE)->whereIn('token_id', $keys)->pluck($column, 'token_id')->all(), is_string(...));
    }

    /** The keys of the revoked tokens, whose identifiers stay with the record of their revocation. */
    private function revoked(): Builder
    {
        return DB::table(TokenRevocations::TABLE)->select('token_id');
    }
}
