<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sanctum Hardening for Laravel
    |--------------------------------------------------------------------------
    | Configuration for the package. Published to config/sanctum-hardening.php with
    | `php artisan vendor:publish --tag=sanctum-hardening-config`.
    | Read environment values with env() here and nowhere else. Outside a config
    | file env() returns null the moment the host app caches its config, so the
    | value silently collapses to its default in production and nowhere else.
    */

    /*
    | The switch for the API. Off, SanctumHardening::routes() registers nothing, so the routes
    | of the API do not exist at all: no 403 and no 404 from a route that refuses, no route. A
    | switch is read typed, never cast: env() turns only `true`, `false`, `(true)`, `(false)`,
    | `empty` and `null` into values, and a cast to bool makes `no`, `off` and ` false` true.
    | So the spellings that switch it off are named, trimmed and in any case, and anything
    | else keeps the value shipped here.
    */
    'enabled' => ! in_array(
        is_string($enabled = env('SANCTUM_HARDENING_ENABLED')) ? strtolower(trim($enabled)) : $enabled,
        [false, '', '0', 'false', 'no', 'off'],
        true,
    ),

    /*
    |--------------------------------------------------------------------------
    | Abilities
    |--------------------------------------------------------------------------
    | Every ability a token can carry, with the label a person reads: a translation key or
    | the text itself. There is no wildcard. A token names what it may do and only narrows
    | what its user may do anyway, so granting one is safe for any user.
    |
    | `reserved` names abilities only the application's own code mints, such as the token a
    | device signs in with. The self-service neither offers them nor lists or revokes a token
    | that carries one. A reserved ability needs no entry in `abilities`.
    */
    'abilities' => [
        // 'orders:read' => 'Read orders',
    ],

    'reserved' => [
        // 'device:sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Minting
    |--------------------------------------------------------------------------
    | A taken-over session could otherwise issue itself a dozen long-lived tokens in seconds,
    | and they would outlive a password change. A user holds at most `max_tokens_per_user`
    | tokens that have not expired, and mints at most `per_minute` of them a minute. Where
    | the application sets a limit of its own, a plan's for instance, the lower one counts.
    | A token minted for another tokenable, a workspace for instance, counts for that one.
    |
    | A token lives for one of the `lifetimes`, in days, and never longer than
    | `max_lifetime`. A token without an expiry exists only where `allow_no_expiry` says so.
    | The self-service mints only behind a password confirmed within `password_timeout`
    | seconds, or Laravel's `auth.password_timeout` where it is null.
    */
    'minting' => [
        'max_tokens_per_user' => is_numeric($cap = env('SANCTUM_HARDENING_MAX_TOKENS_PER_USER')) && (int) $cap >= 1
            ? (int) $cap
            : 20,
        'per_minute' => is_numeric($perMinute = env('SANCTUM_HARDENING_MINT_PER_MINUTE')) && (int) $perMinute >= 1
            ? (int) $perMinute
            : 5,
        'lifetimes' => [30, 90, 365],
        'default_lifetime' => is_numeric($defaultLifetime = env('SANCTUM_HARDENING_DEFAULT_LIFETIME')) && (int) $defaultLifetime >= 1
            ? (int) $defaultLifetime
            : 90,
        'max_lifetime' => is_numeric($maxLifetime = env('SANCTUM_HARDENING_MAX_LIFETIME')) && (int) $maxLifetime >= 1
            ? (int) $maxLifetime
            : 365,
        // Loosens a mint, so only an explicit yes turns it on, and a typo keeps it off.
        'allow_no_expiry' => in_array(
            is_string($noExpiry = env('SANCTUM_HARDENING_ALLOW_NO_EXPIRY')) ? strtolower(trim($noExpiry)) : $noExpiry,
            [true, '1', 'true', 'yes', 'on'],
            true,
        ),
        // Zero asks for the password before every mint.
        'password_timeout' => is_numeric($passwordTimeout = env('SANCTUM_HARDENING_PASSWORD_TIMEOUT')) && (int) $passwordTimeout >= 0
            ? (int) $passwordTimeout
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens of another tokenable
    |--------------------------------------------------------------------------
    | A token can belong to another model than the signed-in user, a workspace for instance.
    | PersonalTokens mints, lists and revokes such a token under the rules above only where
    | the application's Gate allows this ability for the signed-in user, with the tokenable
    | as its argument, so a policy of the tokenable's model answers `manage-tokens` in its
    | method `manageTokens()`. Where the application defines neither, the package refuses.
    */
    'tokenable_ability' => 'manage-tokens',

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    | The named limiter the API's routes run behind, in two stages: each token on its own,
    | and each user over all of their tokens. Without the second, a user gets past every
    | limit of a token by minting another one. A request over either answers `429` with
    | `Retry-After`, as a problem document.
    */
    'throttle' => [
        'limiter' => 'sanctum-hardening',
        'per_token' => is_numeric($perToken = env('SANCTUM_HARDENING_PER_TOKEN')) && (int) $perToken >= 1
            ? (int) $perToken
            : 60,
        'per_user' => is_numeric($perUser = env('SANCTUM_HARDENING_PER_USER')) && (int) $perUser >= 1
            ? (int) $perUser
            : 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Last use
    |--------------------------------------------------------------------------
    | Sanctum writes `last_used_at` on every request a token makes, so a busy token turns
    | each request into a write on the same row. The package lets that write through once
    | every `every` seconds.
    */
    'last_used_at' => [
        'every' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Screen
    |--------------------------------------------------------------------------
    | The view set of the self-service screen, <livewire:sanctum-hardening.tokens />:
    | `wirekit`, `plain`, or `auto` for WireKit where pushery/wirekit is installed.
    */
    'ui' => [
        'variant' => 'auto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | What SanctumHardening::routes() puts around the routes it registers. The throttle of the
    | limiter above comes last on its own, so a renamed limiter needs no second change here.
    | `middleware` takes a list or one middleware as a string; a setting that names none keeps
    | `api` and `auth:sanctum`.
    */
    'routes' => [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum'],
    ],

];
