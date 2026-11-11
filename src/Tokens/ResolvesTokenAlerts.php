<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Database\Eloquent\Model;

/**
 * Who is mailed about a token that expires soon or has gone unused, and where the mail sends them to
 * revoke it.
 *
 * The package binds AlertsToTheHolder, which mails the token's holder where it receives notifications
 * and links to the page `sanctum-hardening.alerts.link` names. An application whose tokens belong to a
 * workspace binds its own, to mail the workspace's owner and link to the workspace's page:
 *
 *     $this->app->bind(ResolvesTokenAlerts::class, WorkspaceTokenAlerts::class);
 */
interface ResolvesTokenAlerts
{
    /**
     * The notifiable the mail about `$token` goes to: a model that routes notifications, as one that
     * uses `Notifiable` does, or an on-demand notifiable. Null where nobody receives it.
     */
    public function recipient(Model $token): ?object;

    /** The absolute address of the page where `$token` is revoked, or null where there is none. */
    public function link(Model $token): ?string;
}
