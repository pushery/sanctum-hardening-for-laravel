---
name: sanctum-hardening-for-laravel
description: >
  Install, configure, and apply the Sanctum Hardening for Laravel package in a Laravel
  application.
license: MIT
metadata:
  author: pushery
---

# Sanctum Hardening for Laravel

Use this skill when a Laravel application installs or integrates the
`pushery/sanctum-hardening-for-laravel` package. The full reference is at
<https://docs.pushery.com/sanctum-hardening-for-laravel/>.

## Primary Goal

Mint, throttle and switch off the application's Sanctum tokens so that a token only narrows what
its user may do, in the smallest correct way.

## Workflow

### 1. Install

Before you start, set up Sanctum. `composer require` installs it along with this package but does
not set it up: `php artisan install:api` creates Sanctum's `personal_access_tokens` table and
`routes/api.php`. The model that holds tokens, usually `App\Models\User`, uses Sanctum's
`HasApiTokens`, or a mint is refused for it. The package's `HasApiKeys` uses Sanctum's trait and
makes `createToken()` hand out a key in the package's format, so it goes in its place:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pushery\SanctumHardening\Tokens\HasApiKeys;

class User extends Authenticatable
{
    use HasApiKeys;
}
```

Every token the package mints is a key, `<product>_<environment>_<id>_<secret><checksum>`, with the
product and the environment from `keys.product` and `keys.environment`. A value whose form or
checksum is wrong, Sanctum's own `<id>|<text>` among them, is refused before the database is asked.
An application with a token model of its own uses `FindsTokensByKey` on it.

```bash
# 1. Set up Sanctum, unless the application already has
php artisan install:api
# 2. Install the package
composer require pushery/sanctum-hardening-for-laravel
# 3. Create the package's tables
php artisan migrate
```

The service provider is registered automatically through package discovery.

### 2. Configure

Publish the configuration file:

```bash
php artisan vendor:publish --tag="sanctum-hardening-config"
```

Every option in `config/sanctum-hardening.php` is documented inline.

The other publish tags:

| Tag | Publishes |
|---|---|
| `sanctum-hardening-views` | the views, to override them in the application |
| `sanctum-hardening-lang` | the translations, to override them in the application |
| `sanctum-hardening-migrations` | the migrations, in the steps below |
| `sanctum-hardening` | every group at once, the migrations included |

The migrations run from the package with `php artisan migrate`, and the package keeps
registering them after they are published. To keep them in the application instead, call
`\Pushery\SanctumHardening\SanctumHardeningServiceProvider::ignoreMigrations()` in the `register`
method of one of the application's service providers before the application's first
`php artisan migrate`, then publish and run them:

```bash
php artisan vendor:publish --tag="sanctum-hardening-migrations"
php artisan migrate
```

Publish them only after that call, through this tag or through the `sanctum-hardening` tag, which
publishes the migrations with every other group. Without the call, the name `vendor:publish` gave
a copy decides what `migrate` does with it. A copy named with the current date runs beside the
package's own file and fails on a table that already exists. A copy that kept the package's file
name is the same migration as that file, and `migrate` runs it once. The migrations create
`sanctum_hardening_key_ids`, `sanctum_hardening_token_prefixes`, `sanctum_hardening_token_revocations`,
`sanctum_hardening_token_networks`, `sanctum_hardening_token_uses`,
`sanctum_hardening_token_alerts` and `sanctum_hardening_token_rotations`.

Publish the migrations once. For a migration a later release adds, which its release notes name,
copy that one file from `vendor/pushery/sanctum-hardening-for-laravel/database/migrations` into
`database/migrations` under a name like the copies already there: renamed to start with the
current date where they carry the date they were published on, under its own name where they
kept theirs. Then run `php artisan migrate`. Publishing the tag again writes a second copy of each
migration it names with the current date, and `migrate` fails on the tables the first copies
created. Where the migrations have already run from the package, copy the files under their own
names instead of publishing them, so the `migrations` table recognizes them as run.

Name every ability in `sanctum-hardening.abilities`, each with its label, and the abilities only
the application's own code mints in `sanctum-hardening.reserved`.

The limits of a mint and the count of wrong passwords on the screen are kept in cache stores. Name a
store of its own in `cache.limiter` that keeps its keys: a database store with a table of its own,
the DynamoDB store, or a Redis connection and database no other store uses, with
`maxmemory-policy noeviction` and `appendonly yes`. Run the default store, which holds the lock of a
mint and the mark of a spent confirmation, on a store that keeps its keys as well. A store that
evicts, restarts without persistence or is flushed by `cache:clear` or `optimize:clear` lets a
holder mint more, try more passwords and spend one confirmation twice. A Redis under `noeviction`
that has filled up refuses every write instead.

On a database store, schedule the pruning of the package's expired keys once a day, or the rows of
past keys stay for good:

```php
use Illuminate\Support\Facades\Schedule;
use Pushery\SanctumHardening\Support\PruneExpiredCacheKeys;

Schedule::call(PruneExpiredCacheKeys::class)->daily();
```

### 3. Apply the package

Mint through `mint()` of `PersonalTokens` on a route behind `password.confirm`, never with
`createToken()` and its wildcard default. Mint a token that belongs to a workspace or another
model through `mintFor()`, and define the Gate ability `manage-tokens` (or a policy method
`manageTokens()`) that says who may manage its tokens. Mint a device or service token through
`mintForSystem()`. Every mint takes the addresses and networks (IPv4, IPv6, CIDR) a token may be used
from as its last argument, at most `sanctum-hardening.minting.max_networks_per_token` of them (50);
`app(TokenNetworks::class)->restrict($token, $networks)` changes them later. Register the API's routes with `SanctumHardening::routes()` in the
`then` callback of `withRouting()` in `bootstrap/app.php`, which puts them under `/api`, behind
`auth:sanctum` and the package's limiter, and registers none while the API is switched off; a route
the route cache still holds answers `404` then.
The limiter counts each token and its holder with the holder's tier: bind a `ResolvesThrottleTier`
that gives a `ThrottleTier` of `ThrottleCounter`s (`perMinute`, `perSecond`, `writesPerMinute`, where `0`
refuses every write, and `burst`) or null, and a holder without one counts against `throttle.per_token`
and `throttle.per_user`.

An application with Livewire embeds the self-service screen, `<livewire:sanctum-hardening.tokens />`, on a
page behind `auth` and `password.confirm`. On a page with a header of its own, pass `:heading="false"`, which
leaves out the screen's own heading and the sentence under it. Where the API runs behind a limiter of the
application's own, bind a `ResolvesQuickstartLimits` that gives that limiter's limits for the screen's holder, so
the quickstart of the screen names them; a null leaves its part of the sentence out.

Run `php artisan sanctum-hardening:doctor` after installing: it names the tokens that already carry
the wildcard or an ability the catalog does not know, each daily command of the package that nothing
in the scheduler runs, the rows a prune should have deleted, and the mail after a silence where
Sanctum writes no last use (`sanctum.last_used_at` off). Name a command the server runs from a
cron line with `--scheduled-elsewhere=prune-uses`. Schedule `sanctum-hardening:prune-prefixes`
beside `sanctum:prune-expired`, whose query deletes leave the prefixes of the pruned tokens behind, and
`sanctum-hardening:prune-revocations`. A revoked token is not deleted: it is expired and recorded with
the user who revoked it; revoke from the application's own code through
`app(TokenRevocations::class)->revoke($token, $by)`, which answers `false` and changes nothing for a
token revoked before. Every request through `SanctumHardening::routes()` is
recorded with its route, method and status, never the values it carried, and the screen lists the
latest of each token; schedule `sanctum-hardening:prune-uses` daily to delete the older ones.
Schedule `sanctum-hardening:send-alerts` daily and name the page that carries the screen in
`sanctum-hardening.alerts.link`: it mails a token's holder before the token expires and once it has
gone unused, each once. The alerts and the prunes run once at a time through a lock in the default
cache store, so servers that run the scheduler together need a store they share. Bind a
`ResolvesTokenAlerts` of the application's own where a token's holder is a workspace rather than a
person.

## Examples

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Pushery\SanctumHardening\Tokens\PersonalTokens;

Route::post('/settings/tokens', function (Request $request, PersonalTokens $tokens) {
    return ['token' => $tokens->mint($request, 'CI', ['orders:read'], 90)->plainTextToken];
})->middleware(['auth', 'password.confirm']);
```

```php
// bootstrap/app.php
use Illuminate\Support\Facades\Route;
use Pushery\SanctumHardening\SanctumHardening;

->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        SanctumHardening::routes(function () {
            Route::get('/orders', fn () => ['orders' => []]);
        });
    },
)
```

## Anti-Patterns

- Calling `createToken()` without abilities: Sanctum gives the token the wildcard, and every
  `tokenCan()` answers yes for it. Mint through `PersonalTokens` with the abilities it needs.
- Minting on a route without `password.confirm`: the mint is refused, because a taken-over session
  must not issue itself tokens.
- Registering the API's routes by hand around `SanctumHardening::routes()`: they stay when the API
  is switched off, and they run without the per-user limit.
- Calling `SanctumHardening::routes()` inside `routes/api.php` under the shipped settings: Laravel
  loads that file under the prefix `api` already, so the routes answer under `/api/api/`. Call it in
  `bootstrap/app.php`, or set `routes.prefix` to `''`, `routes.middleware` to `['auth:sanctum']` and
  `ui.endpoint` to the API's address for a call in that file.
- Keeping Sanctum's `HasApiTokens` on a model whose tokens the application's own code mints with
  `createToken()`: the token is `<id>|<text>`, which the package's token model refuses. Use
  `HasApiKeys`, which keeps Sanctum's trait.
- Setting a token model of one's own without `FindsTokensByKey`: Sanctum's `findToken()` reads the
  running number before a `|`, so no key is ever found.
- Offering a reserved ability in a form: it is refused, and a token that carries one never appears
  in the self-service.
- Minting a workspace's token for the owner or through `mintForSystem()`: the first ends when the
  owner leaves, the second skips the limits and the password. `mintFor()` keeps both.
- Keeping `cache.limiter` or the default store on a store that loses keys before their time: a Redis
  whose `maxmemory-policy` is anything but `noeviction` or that runs without `appendonly yes`, or
  Memcached. A lost count gives a holder a fresh minute of mints and of wrong passwords, and a lost
  mark lets one confirmation cover two mints.
