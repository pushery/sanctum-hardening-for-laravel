<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\RoutesNotifications;
use Illuminate\Routing\Router;

/**
 * Mails the holder of a token, where it receives notifications, and links to the page the configuration
 * names: `sanctum-hardening.alerts.link`, a route name or an address.
 */
final readonly class AlertsToTheHolder implements ResolvesTokenAlerts
{
    public function __construct(
        private Repository $config,
        private Router $router,
        private UrlGenerator $url,
    ) {}

    public function recipient(Model $token): ?object
    {
        $holder = $token->getAttribute('tokenable');

        return is_object($holder) && in_array(RoutesNotifications::class, class_uses_recursive($holder), true) ? $holder : null;
    }

    public function link(Model $token): ?string
    {
        $link = $this->config->get('sanctum-hardening.alerts.link');

        if (! is_string($link) || trim($link) === '') {
            return null;
        }

        $link = trim($link);

        return $this->router->has($link) ? $this->url->route($link) : $this->url->to($link);
    }
}
