<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Livewire;

use Composer\InstalledVersions;
use Pushery\WireKit\WireKitServiceProvider;

/**
 * Whether the screen's WireKit view renders on the WireKit the application has installed, which `ui.variant` set to
 * `auto` asks before it takes that view over the plain one.
 *
 * The view lists the latest requests of a token through the `items` of `x-wirekit::list`, a prop WireKit has had since
 * FLOOR. An older WireKit takes the list for an attribute and fails on printing it, so the screen would break as soon
 * as a token had a request to show; `auto` renders the plain view there instead.
 *
 * @internal
 */
final class WireKitView
{
    /** The oldest WireKit the view renders on. */
    public const string FLOOR = '2.67.0';

    /** Whether the WireKit the application has installed renders the view. */
    public static function installed(): bool
    {
        $version = InstalledVersions::isInstalled('pushery/wirekit') ? InstalledVersions::getVersion('pushery/wirekit') : null;

        return self::rendersOn(class_exists(WireKitServiceProvider::class), $version);
    }

    /**
     * Whether the view renders on a WireKit: one that is installed, in FLOOR or later. `$version` is the one Composer
     * installed, as Composer writes it, `2.73.0.0` for a release, or null where Composer names none. A version that
     * starts with no digit names a branch, `dev-main` for instance, and counts as recent enough, since nothing says it
     * is older.
     */
    public static function rendersOn(bool $installed, ?string $version): bool
    {
        return $installed && ($version === null || preg_match('/^\d/', $version) !== 1 || version_compare($version, self::FLOOR, '>='));
    }
}
