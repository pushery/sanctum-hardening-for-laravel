# Changelog

All notable changes to `pushery/sanctum-hardening-for-laravel` are documented here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

A release carries only the sections that have an entry, in this order: `### ⚠️ Breaking`, `### ✨ Added`, `### 🔧 Changed`, `### ⚡ Performance`, `### 🐛 Fixed`, `### 🔒 Security`, `### 🧹 Deprecated`, `### 🗑️ Removed` and `### 📚 Documentation`.

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

[Unreleased]: https://github.com/pushery/sanctum-hardening-for-laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/pushery/sanctum-hardening-for-laravel/releases/tag/v0.1.0
