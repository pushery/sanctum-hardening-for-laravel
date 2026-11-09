<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Support\TableLookup;
use Pushery\SanctumHardening\Tokens\NetworksCallback;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Pushery\SanctumHardening\Tokens\Tokens;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Reports the tokens minted outside the package: a `createToken()` with the wildcard, Sanctum's
 * default, or with an ability the catalog does not know. Such a token passes every `tokenCan()` or
 * none of them, and nothing else says so. It reports as well the tokens limited to networks while
 * Sanctum's authentication callback is no longer the one the package wrapped, because Sanctum then
 * accepts them from any address.
 */
#[AsCommand(name: 'sanctum-hardening:doctor')]
final class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'sanctum-hardening:doctor';

    /** @var string */
    protected $description = 'Report the tokens that carry the wildcard or an ability the catalog does not know, and limited tokens Sanctum no longer checks';

    public function handle(AbilityCatalog $catalog): int
    {
        $model = Tokens::model();
        $limited = $this->limitedWithoutTheCheck();
        $wildcard = [];
        $unknown = [];
        $exposed = [];
        $count = 0;

        foreach ($model->newQuery()->select([$model->getKeyName(), 'abilities'])->lazyById(500, $model->getKeyName()) as $token) {
            $count++;
            $abilities = Tokens::abilitiesOf($token);
            $key = TokenPrefixes::key($token->getKey());

            if (in_array(AbilityCatalog::WILDCARD, $abilities, true)) {
                $wildcard[] = $key;
            } elseif (array_any($abilities, static fn (mixed $ability): bool => ! is_string($ability) || ! $catalog->has($ability))) {
                $unknown[] = $key;
            }

            if (isset($limited[$key])) {
                $exposed[] = $key;
            }
        }

        if ($wildcard === [] && $unknown === [] && $exposed === []) {
            $this->components->info("All {$count} tokens carry abilities from the catalog.");

            return self::SUCCESS;
        }

        if ($wildcard !== []) {
            $this->components->error(count($wildcard).' token(s) carry the wildcard, so every tokenCan() answers yes for them: '.implode(', ', $wildcard).'. Mint them again through the package with the abilities they need.');
        }

        if ($unknown !== []) {
            $this->components->error(count($unknown).' token(s) carry an ability the catalog does not know: '.implode(', ', $unknown).'. Add the ability to the catalog, or mint the tokens again.');
        }

        if ($exposed !== []) {
            $this->components->error(count($exposed).' token(s) are limited to networks, but Sanctum\'s authentication callback is no longer the one the package wrapped, so they are accepted from any address: '.implode(', ', $exposed).'. Set the application\'s callback in a service provider\'s boot(), where the package wraps it.');
        }

        return self::FAILURE;
    }

    /**
     * The keys of the tokens limited to networks while Sanctum's authentication callback is not the
     * package's, keyed by themselves; none while it is, or before the package's table exists.
     *
     * @return array<string, string>
     */
    private function limitedWithoutTheCheck(): array
    {
        if (Sanctum::$accessTokenAuthenticationCallback instanceof NetworksCallback || ! TableLookup::exists(TokenNetworks::TABLE)) {
            return [];
        }

        $keys = array_filter(DB::table(TokenNetworks::TABLE)->pluck('token_id')->all(), is_string(...));

        return array_combine($keys, $keys);
    }
}
