<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Pushery\SanctumHardening\Tokens\Tokens;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Reports the tokens minted outside the package: a `createToken()` with the wildcard, Sanctum's
 * default, or with an ability the catalog does not know. Such a token passes every `tokenCan()` or
 * none of them, and nothing else says so.
 */
#[AsCommand(name: 'sanctum-hardening:doctor')]
final class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'sanctum-hardening:doctor';

    /** @var string */
    protected $description = 'Report the tokens that carry the wildcard or an ability the catalog does not know';

    public function handle(AbilityCatalog $catalog): int
    {
        $model = Tokens::model();
        $wildcard = [];
        $unknown = [];
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
        }

        if ($wildcard === [] && $unknown === []) {
            $this->components->info("All {$count} tokens carry abilities from the catalog.");

            return self::SUCCESS;
        }

        if ($wildcard !== []) {
            $this->components->error(count($wildcard).' token(s) carry the wildcard, so every tokenCan() answers yes for them: '.implode(', ', $wildcard).'. Mint them again through the package with the abilities they need.');
        }

        if ($unknown !== []) {
            $this->components->error(count($unknown).' token(s) carry an ability the catalog does not know: '.implode(', ', $unknown).'. Add the ability to the catalog, or mint the tokens again.');
        }

        return self::FAILURE;
    }
}
