# Changelog

All notable changes to `pushery/sanctum-hardening-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

## [0.2.0] - 2026-11-09

### ⚠️ Breaking

- **Revoking a token no longer deletes it.** `PersonalTokens::revoke()` and the screen's button expire the token at that moment, which Sanctum refuses from then on, and record the revocation. A revoked token leaves the list, and keeps its row and its prefix until it is pruned.

  **Upgrade:** code that reads a holder's tokens through `tokens()` sees a revoked token until `sanctum-hardening:prune-revocations` deletes it, and tells it apart by its `expires_at`, which lies in the past.

### ✨ Added

- **The requests each token made.** Every request through `SanctumHardening::routes()` is recorded with the route's name or URI pattern, the method, the status and the time, never the address with its values or the body, at most once every `usage.every` seconds for the same ones. `TokenUses::latest()` reads the newest, `TokenUses::usedBetween()` says whether a token was used in a period, and the screen shows the latest requests of each token. `RecordsTokenUse` records a route of the application's own, and `sanctum-hardening:prune-uses` deletes the uses older than `usage.keep_days` days, 30 by default.

  **Upgrade:** run `php artisan migrate` for the table `sanctum_hardening_token_uses`, which `2026_11_08_000002_create_sanctum_hardening_token_uses_table` creates, and schedule `sanctum-hardening:prune-uses` daily. A host that published the screen's views publishes them again for the latest requests.

- **A token can be limited to addresses and networks.** Every mint takes the IPv4 and IPv6 addresses and the CIDR networks a token may be used from, and `TokenNetworks::restrict()` changes them later. A request from anywhere else is refused the way an unknown token is, inside Sanctum's authentication callback, where a callback the application set in its own `boot()` keeps answering. The address is the request's through the proxies the application trusts. `sanctum-hardening:prune-prefixes` forgets the networks of deleted tokens as well, and `sanctum-hardening:doctor` names the limited tokens while Sanctum's callback is not the package's, which accepts them from any address.

  The screen asks for the networks of a new token in an optional field, one a line, answers an entry that is no address and no network on its status line, and lists the networks of each limited token.

  **Upgrade:** run `php artisan migrate` for the table `sanctum_hardening_token_networks`, which `2026_11_08_000001_create_sanctum_hardening_token_networks_table` creates. A host that published the screen's views publishes them again for the networks field. The package now declares `symfony/http-foundation`, `ext-ctype` and `ext-filter`, which a Laravel 13 application has already.

- **A record of every revoked token.** `TokenRevocations` names the token, its holder, the signed-in user who revoked it and the time, and `TokenRevocations::of($tokenable)` reads them back, the latest first. The application's own code revokes the same way through `TokenRevocations::revoke($token, $by)`. `sanctum-hardening:prune-revocations` deletes a revoked token with its record once `revocations.keep_days` days have passed, 90 by default.

  **Upgrade:** run `php artisan migrate` for the table `sanctum_hardening_token_revocations`, which `2026_11_08_000000_create_sanctum_hardening_token_revocations_table` creates, and schedule `sanctum-hardening:prune-revocations` daily.

### 🐛 Fixed

- **The revoke button of the WireKit token screen is drawn as a danger.** It set `variant`, a prop WireKit's button does not have, so the word landed among the button's HTML attributes, the button looked like every other one, and a host that lints the views of its packages with `wirekit:doctor:props` went red. It sets `intent` now.

### 📚 Documentation

- **The token screen is shown on its page.** The image is rendered from the package itself, over tokens minted through it, nothing in it drawn by hand.

## [0.1.0] - 2026-11-08

### ✨ Added

- **An ability catalog without a wildcard.** Every ability a token can carry is named in `sanctum-hardening.abilities` or with `AbilityCatalog::define()`, and minting refuses the wildcard, an unknown ability and none at all with an `AbilityRefused`. A reserved ability is minted only by the application's own code, and the self-service screen neither lists nor revokes a token that carries one. `sanctum-hardening:doctor` names the tokens minted outside the package with the wildcard or an unknown ability.
- **Minting with limits.** `PersonalTokens::mint()` mints for the signed-in user behind a password confirmed within the timeout, at most `max_tokens_per_user` tokens that have not expired and `per_minute` mints a minute, one at a time, with a lifetime from `minting.lifetimes`. A resolver of the application can lower the limit, and `mintForSystem()` mints for the application's own code. Each token keeps the first twelve characters of its secret, so a person can tell their tokens apart.
- **Tokens of a workspace.** `PersonalTokens::mintFor()` mints a token that belongs to a tokenable other than the signed-in user, a workspace for instance, under the rules of `mint()` counted for that tokenable and behind the signed-in user's confirmed password. The application decides who may do so with the Gate ability `manage-tokens`, named by `tokenable_ability`, or a policy method `manageTokens()`, and where it defines neither, every such mint is refused as `not_allowed`. `listFor()` and `revoke()` take the tokenable as their last argument.
- **Throttling in two stages.** The API registered with `SanctumHardening::routes()` runs behind a limit per token and one per user over all of their tokens, and a request over either is answered with `429` and `Retry-After` as a problem document.
- **A switch that removes the API.** With `SANCTUM_HARDENING_ENABLED=false`, `SanctumHardening::routes()` registers no route at all.
- **An optional self-service screen.** `<livewire:sanctum-hardening.tokens />` mints, lists and revokes the signed-in user's tokens through `PersonalTokens`, shows a new token's plain text once and never offers or lists a reserved ability, in a WireKit view and a plain one chosen by `ui.variant`. It shows when a token expires and when it was last used in the reader's language, with "Uhr" after a German clock time.
- **The prefixes of deleted tokens are pruned.** `sanctum-hardening:prune-prefixes` forgets the prefix of every token that no longer exists, the ones a query deleted included, as `sanctum:prune-expired` deletes them. Schedule it beside Sanctum's prune.
- **The last use of a token written once a minute.** Sanctum's write of `last_used_at` on every request goes through once every `last_used_at.every` seconds.

[Unreleased]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pushery/sanctum-hardening-for-laravel/releases/tag/v0.1.0
