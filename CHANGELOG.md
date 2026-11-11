# Changelog

All notable changes to `pushery/sanctum-hardening-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

## [0.4.0] - 2026-11-11

### ✨ Added

- **Mails before a token expires and after it has gone unused.** `sanctum-hardening:send-alerts` mails the holder of a token `alerts.expiring.days` days before it expires, 7 by default, and once no request has used it for `alerts.inactive.days` days, 90 by default, a token never used counted from its creation. Each mail names the token and the first characters of its secret, links to the page `alerts.link` names, where the holder revokes it, and goes out once for the same expiry and the same last use. `enabled` switches either mail off, and a token with a reserved ability gets none. An application whose tokens belong to a workspace binds its own `ResolvesTokenAlerts` to name the recipient and the page. `sanctum-hardening:prune-prefixes` forgets the alerts of deleted tokens as well.

  **Upgrade:** run `php artisan migrate` for the table `sanctum_hardening_token_alerts`, which `2026_11_08_000003_create_sanctum_hardening_token_alerts_table` creates, name the page with the screen in `sanctum-hardening.alerts.link`, and schedule `sanctum-hardening:send-alerts` daily. The package now requires `illuminate/notifications`, which a Laravel application already has through `laravel/framework`.

## [0.3.0] - 2026-11-10

### ✨ Added

- **The screen manages the tokens of another holder.** `<livewire:sanctum-hardening.tokens :tokenable="$workspace" />` mints, lists and revokes the tokens of the model it is given, a workspace for instance, where the application's Gate lets the signed-in user, through `mintFor()`, `listFor()` and `revoke()`. The holder stays the one the page passed, and a request cannot point the screen at another. Its texts then speak of the holder rather than of the account, from the new lines under `screen.holder` an application can publish and word for its own holder.
- **The screen asks for the password itself where it was not confirmed a moment ago.** It shows a password field above the form, checks the password against the one the user's model holds, records the confirmation in the session of the page's host, and allows five wrong passwords a minute for the user and the address. A page on a host without `password.confirm`, the console of a workspace on its own subdomain for instance, no longer runs into a loop. `PersonalTokens::confirmPassword()` does the same for an application's own screen, and `PersonalTokens::passwordConfirmed()` says whether a request carries a fresh confirmation.
- **`ui.endpoint` names the address of the quickstart.** An API that answers on a host of its own, such as `https://api.example.com/api`, is named there, and the quickstart no longer shows an address on the page's host where the API does not answer. Left null, the quickstart names the routes' prefix on that host, as before.

### 🔧 Changed

- **The two switches read their rule from the package, so a fix to it reaches a published config.** `config/sanctum-hardening.php` spelled out how a value of the `.env` turns them on or off, and a host that published the file kept that rule as it was on the day it published it, so a later fix to the rule, such as the one for an empty `.env` line, never reached the copy. The config calls `EnvSwitch::read()` now, and the rule ships in `src/Support/EnvSwitch.php`: `false`, `0`, `no` and `off` switch off, `true`, `1`, `yes` and `on` switch on, trimmed and in any case, and anything else keeps the value the package ships. **Upgrade:** in a published copy, replace the expression of each once with its call and import the class: `enabled` with `EnvSwitch::read(env('SANCTUM_HARDENING_ENABLED'), true)` and `minting.allow_no_expiry` with `EnvSwitch::read(env('SANCTUM_HARDENING_ALLOW_NO_EXPIRY'), false)`, and `use Pushery\SanctumHardening\Support\EnvSwitch;` at the top.

### 🐛 Fixed

- **An empty `SANCTUM_HARDENING_ENABLED=` keeps the API on.** `env()` reads the line as an empty string, and the switch counted it as off, so a line left empty by copying a template of the `.env` removed the routes of the API while a missing line kept them. Only `false`, `0`, `no` and `off` switch it off now.

### 📚 Documentation

- **The docs say when the row of a revoked token goes.** The README and the page on minting said a revoked token keeps its row until `revocations.keep_days` has passed, while `sanctum:prune-expired --hours=24`, the schedule the docs show, deletes it after a day, because a revoked token is expired from the moment it is revoked. Both say so now, as the start page did, and name the setting that keeps the row as long as the record of the revocation: `--hours` at `revocations.keep_days` times 24, which keeps every expired token that long.

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

[Unreleased]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pushery/sanctum-hardening-for-laravel/releases/tag/v0.1.0
