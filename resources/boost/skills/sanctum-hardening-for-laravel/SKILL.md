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

```bash
composer require pushery/sanctum-hardening-for-laravel
php artisan migrate
```

The service provider is registered automatically through package discovery.

### 2. Configure

Publish everything at once, or one group at a time:

```bash
php artisan vendor:publish --tag="sanctum-hardening"
php artisan vendor:publish --tag="sanctum-hardening-config"
php artisan vendor:publish --tag="sanctum-hardening-migrations"
php artisan vendor:publish --tag="sanctum-hardening-views"
php artisan vendor:publish --tag="sanctum-hardening-lang"
```

Name every ability in `sanctum-hardening.abilities`, each with its label, and the abilities only
the application's own code mints in `sanctum-hardening.reserved`.

### 3. Apply the package

Mint through `PersonalTokens::mint()` on a route behind `password.confirm`, never with
`createToken()` and its wildcard default. Mint a token that belongs to a workspace or another
model through `mintFor()`, and define the Gate ability `manage-tokens` (or a policy method
`manageTokens()`) that says who may manage its tokens. Mint a device or service token through
`mintForSystem()`. Every mint takes the addresses and networks (IPv4, IPv6, CIDR) a token may be used
from as its last argument; `TokenNetworks::restrict()` changes them later. Register the API's routes with `SanctumHardening::routes()`, which puts them
behind `auth:sanctum` and the package's limiter and registers none while the API is switched off.

An application with Livewire embeds the self-service screen, `<livewire:sanctum-hardening.tokens />`, on a
page behind `auth` and `password.confirm`.

Run `php artisan sanctum-hardening:doctor` after installing: it names the tokens that already carry
the wildcard or an ability the catalog does not know. Schedule `sanctum-hardening:prune-prefixes`
beside `sanctum:prune-expired`, whose query deletes leave the prefixes of the pruned tokens behind, and
`sanctum-hardening:prune-revocations`. A revoked token is not deleted: it is expired and recorded with
the user who revoked it; revoke from the application's own code through
`TokenRevocations::revoke($token, $by)`. Every request through `SanctumHardening::routes()` is
recorded with its route, method and status, never the values it carried, and the screen lists the
latest of each token; schedule `sanctum-hardening:prune-uses` daily to delete the older ones.
Schedule `sanctum-hardening:send-alerts` daily and name the page that carries the screen in
`sanctum-hardening.alerts.link`: it mails a token's holder before the token expires and once it has
gone unused, each once. Bind a `ResolvesTokenAlerts` of the application's own where a token's holder
is a workspace rather than a person.

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
use Illuminate\Support\Facades\Route;
use Pushery\SanctumHardening\SanctumHardening;

SanctumHardening::routes(function () {
    Route::get('/orders', fn () => ['orders' => []]);
});
```

## Anti-Patterns

- Calling `createToken()` without abilities: Sanctum gives the token the wildcard, and every
  `tokenCan()` answers yes for it. Mint through `PersonalTokens` with the abilities it needs.
- Minting on a route without `password.confirm`: the mint is refused, because a taken-over session
  must not issue itself tokens.
- Registering the API's routes by hand around `SanctumHardening::routes()`: they stay when the API
  is switched off, and they run without the per-user limit.
- Offering a reserved ability in a form: it is refused, and a token that carries one never appears
  in the self-service.
- Minting a workspace's token for the owner or through `mintForSystem()`: the first ends when the
  owner leaves, the second skips the limits and the password. `mintFor()` keeps both.
