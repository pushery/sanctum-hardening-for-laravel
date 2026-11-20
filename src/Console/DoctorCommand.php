<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Support\TableLookup;
use Pushery\SanctumHardening\Tokens\Moments;
use Pushery\SanctumHardening\Tokens\NetworksCallback;
use Pushery\SanctumHardening\Tokens\TokenAlerts;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Pushery\SanctumHardening\Tokens\TokenRevocations;
use Pushery\SanctumHardening\Tokens\Tokens;
use Pushery\SanctumHardening\Tokens\TokenUses;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Reports the tokens minted outside the package: a `createToken()` with the wildcard, Sanctum's
 * default, or with an ability the catalog does not know. Such a token passes every `tokenCan()` or
 * none of them, and nothing else says so. It reports as well the tokens limited to networks while
 * Sanctum's authentication callback is no longer the one the package wrapped, because Sanctum then
 * accepts them from any address.
 *
 * It also reports what the retention of recorded requests and revocations depends on: each daily
 * command of the package that nothing in the scheduler runs, and the rows a prune should have deleted.
 * A prune that never runs, or fails every night, would otherwise keep those rows for good, and nothing
 * would say so. And it reports the alert after a silence where it is switched on while Sanctum writes no
 * last use, because sanctum-hardening:send-alerts then sends none. On PostgreSQL it warns where the
 * database session runs in another time zone than the application. And it reports a guard Sanctum asks
 * for a session user while no route signs a client in over the session, because a raw remember cookie
 * then signs in to the API without the application key.
 */
#[AsCommand(name: 'sanctum-hardening:doctor')]
final class DoctorCommand extends Command
{
    /** The commands of the package a host runs daily, by the name `--scheduled-elsewhere` takes. */
    private const array DAILY = ['prune-prefixes', 'prune-revocations', 'prune-uses', 'send-alerts'];

    /** @var string */
    protected $signature = 'sanctum-hardening:doctor
        {--scheduled-elsewhere=* : A daily command of the package that your server runs outside the scheduler, such as prune-uses}';

    /** @var string */
    protected $description = 'Report the tokens that carry the wildcard or an ability the catalog does not know, limited tokens Sanctum no longer checks, prunes that do not run, and a session guard no route needs';

    public function handle(AbilityCatalog $catalog, Schedule $schedule, TokenAlerts $alerts, Repository $config, Router $router): int
    {
        $tokens = $this->tokensAreSound($catalog);
        $scheduled = $this->dailyCommandsAreScheduled($schedule, $alerts);
        $pruned = $this->nothingOutlivesItsRetention($config);
        $silence = $this->silenceIsKnown($alerts);
        $guards = $this->sessionGuardsAreNeeded($config, $router);
        $this->warnOfAnotherSessionZone();

        return $tokens && $scheduled && $pruned && $silence && $guards ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Report the guards Sanctum asks for a session user while no route signs a client in over the session. On a route
     * without a session, as in the api group, the web guard takes a raw remember cookie for a login, so a guard is needed
     * only where a route runs behind EnsureFrontendRequestsAreStateful, which Sanctum's statefulApi() puts there.
     */
    private function sessionGuardsAreNeeded(Repository $config, Router $router): bool
    {
        // Sanctum takes one name or a list, and asks the web guard where the setting is missing.
        $configured = $config->get('sanctum.guard', 'web');
        $guards = [];

        foreach (is_array($configured) ? $configured : [$configured] as $guard) {
            if (is_string($guard) && trim($guard) !== '') {
                $guards[] = trim($guard);
            }
        }

        if ($guards === [] || $this->someRouteIsStateful($router)) {
            return true;
        }

        $this->components->error('Sanctum asks the guard '.implode(', ', $guards)." for a session user before it reads a bearer token, and no route signs a client in over the session, so a raw remember cookie signs in to the API without the application key. Leave sanctum-hardening.session_guards empty, or sign the client in through Sanctum's statefulApi().");

        return false;
    }

    /** Whether a middleware group or a route runs EnsureFrontendRequestsAreStateful. */
    private function someRouteIsStateful(Router $router): bool
    {
        $names = [];

        foreach ($router->getMiddlewareGroups() as $group) {
            array_push($names, ...array_values(is_array($group) ? $group : []));
        }

        foreach ($router->getRoutes()->getRoutes() as $route) {
            array_push($names, ...array_values($route->gatherMiddleware()));
        }

        return array_any($names, $this->isStateful(...));
    }

    /**
     * Whether a middleware a group or a route lists is EnsureFrontendRequestsAreStateful, bare or with parameters.
     *
     * @param  mixed  $name  a class name, an alias with its parameters or a closure, as a group or a route lists it
     */
    private function isStateful(mixed $name): bool
    {
        $stateful = EnsureFrontendRequestsAreStateful::class;

        return is_string($name) && ($name === $stateful || str_starts_with($name, $stateful.':'));
    }

    /**
     * Warn where the database session runs in another time zone than the application, on PostgreSQL. The package
     * binds its own moments with their offset there, so its tables hold them whatever the session's zone, and the
     * warning changes no exit code. Laravel binds every other moment without an offset, which the session reads in
     * its own zone, so a moment stored that way, the application's own or one of the package's stored without its
     * offset, sits as far from itself as the two zones stand apart. Compared as offsets at this moment, so two names
     * of one zone agree.
     */
    private function warnOfAnotherSessionZone(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $offset = $connection->scalar('select extract(timezone from now())::integer');
        $application = Date::now();

        if (! is_numeric($offset) || (int) $offset === $application->getOffset()) {
            return;
        }

        $zone = $connection->scalar('show timezone');

        $this->components->warn("The database session runs in the time zone '".(is_string($zone) ? $zone : (string) $offset)."', the application in '".$application->getTimezone()->getName()."'. The package writes its own moments with their offset, so its tables hold them, and a moment stored without its offset keeps the shift between the two zones. Laravel writes every other moment without an offset, which the session reads in its own zone: set the timezone key of the connection in config/database.php to the application's zone.");
    }

    /**
     * Report the alert after a silence where it is switched on while Sanctum writes no last use, which
     * sanctum-hardening:send-alerts then leaves out; whether it is not so.
     */
    private function silenceIsKnown(TokenAlerts $alerts): bool
    {
        if (! $alerts->enabled(TokenAlerts::INACTIVE) || Tokens::lastUseIsRecorded()) {
            return true;
        }

        $this->components->error('The alert after a silence is switched on, but Sanctum writes no last use while sanctum.last_used_at is off, so sanctum-hardening:send-alerts sends no such alert. Switch it off with sanctum-hardening.alerts.inactive.enabled, or let Sanctum write the last use.');

        return false;
    }

    /** Report the tokens with the wildcard, an unknown ability or networks Sanctum no longer checks; whether there are none. */
    private function tokensAreSound(AbilityCatalog $catalog): bool
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

            $names = array_filter($abilities, is_string(...));

            if (in_array(AbilityCatalog::WILDCARD, $abilities, true)) {
                $wildcard[] = $key;
            } elseif ($names !== $abilities || array_any($names, static fn (string $ability): bool => ! $catalog->has($ability))) {
                $unknown[] = $key;
            }

            if (isset($limited[$key])) {
                $exposed[] = $key;
            }
        }

        if ($wildcard === [] && $unknown === [] && $exposed === []) {
            $this->components->info("All {$count} tokens carry abilities from the catalog.");

            return true;
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

        return false;
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

    /**
     * Report each daily command of the package that no entry of the scheduler runs; whether there is
     * none. An entry counts wherever its command line names the command, under a tenancy runner too.
     * A command the server runs outside the scheduler, from a cron line for instance, is named with
     * `--scheduled-elsewhere`, and `send-alerts` is left out while both alerts are switched off.
     */
    private function dailyCommandsAreScheduled(Schedule $schedule, TokenAlerts $alerts): bool
    {
        $lines = [];

        foreach ($schedule->events() as $event) {
            $lines[] = (string) $event->command;
        }

        $elsewhere = $this->scheduledElsewhere();
        $alerting = $alerts->enabled(TokenAlerts::EXPIRING) || $alerts->enabled(TokenAlerts::INACTIVE);
        $missing = [];

        foreach (self::DAILY as $name) {
            $command = 'sanctum-hardening:'.$name;

            if (($name === 'send-alerts' && ! $alerting) || in_array($name, $elsewhere, true) || array_any($lines, static fn (string $line): bool => str_contains($line, $command))) {
                continue;
            }

            $missing[] = $command;
        }

        if ($missing === []) {
            return true;
        }

        $this->components->error('Nothing in the scheduler runs '.implode(', ', $missing).'. Schedule each of them daily, or name the ones your server runs outside the scheduler with --scheduled-elsewhere.');

        return false;
    }

    /**
     * The commands named with `--scheduled-elsewhere`, without the package's prefix: the option may be
     * repeated, and each value may list several, separated by commas.
     *
     * @return list<string>
     */
    private function scheduledElsewhere(): array
    {
        $names = [];

        foreach ($this->option('scheduled-elsewhere') as $value) {
            foreach (explode(',', is_string($value) ? $value : '') as $name) {
                $names[] = str_replace('sanctum-hardening:', '', trim($name));
            }
        }

        return $names;
    }

    /**
     * Report the rows a prune should have deleted: requests recorded longer ago than `usage.keep_days`
     * days and revocations older than `revocations.keep_days` days, each with a day's grace for a prune
     * that runs daily; whether there are none. A table that does not exist holds none.
     */
    private function nothingOutlivesItsRetention(Repository $config): bool
    {
        $usage = $this->days($config, 'usage.keep_days', 30);
        $revocations = $this->days($config, 'revocations.keep_days', 90);
        $kept = true;

        if (TableLookup::exists(TokenUses::TABLE) && DB::table(TokenUses::TABLE)->where('used_at', '<', Moments::bound(Date::now()->subDays($usage + 1)))->exists()) {
            $this->components->error('Requests recorded more than '.($usage + 1)." days ago are still kept, past usage.keep_days of {$usage}, so sanctum-hardening:prune-uses has not run or has failed.");
            $kept = false;
        }

        if (TableLookup::exists(TokenRevocations::TABLE) && DB::table(TokenRevocations::TABLE)->where('revoked_at', '<', Moments::bound(Date::now()->subDays($revocations + 1)))->exists()) {
            $this->components->error('Tokens revoked more than '.($revocations + 1)." days ago are still kept, past revocations.keep_days of {$revocations}, so sanctum-hardening:prune-revocations has not run or has failed.");
            $kept = false;
        }

        return $kept;
    }

    /** A retention in days from the settings, or the shipped value where the setting is no whole number of one or more. */
    private function days(Repository $config, string $key, int $shipped): int
    {
        $value = $config->get('sanctum-hardening.'.$key);

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }
}
