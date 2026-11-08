<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Trop de requêtes. Une autre peut être envoyée une fois écoulé le délai de l’en-tête Retry-After.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'Jetons d’API',
        'intro' => 'Un jeton peut ce que nomment ses capacités, et jamais plus que ton compte.',
        'name' => 'Nom',
        'abilities' => 'Capacités',
        'lifetime' => 'Durée',
        'days' => ':count jour|:count jours',
        'no_expiry' => 'Sans expiration',
        'mint' => 'Créer un jeton',
        'copy_now' => 'Copie le jeton maintenant. Il n’est affiché que cette fois.',
        'minted' => 'Le jeton a été créé.',
        'revoke' => 'Révoquer',
        'revoke_named' => 'Révoquer :name',
        'confirm_revoke' => 'Révoquer ce jeton ? Les requêtes qui l’utilisent cesseront de fonctionner.',
        'revoked' => 'Le jeton a été révoqué.',
        'not_revoked' => 'Aucun jeton n’a été révoqué.',
        'none' => 'Aucun jeton pour l’instant.',
        'prefix' => 'Préfixe',
        'expires' => 'Expire',
        'last_used' => 'Dernière utilisation',
        'never' => 'Jamais',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Démarrage rapide',
        'quickstart_endpoint' => 'Envoie une requête avec le jeton à',
        'quickstart_limits' => 'Chaque jeton fait jusqu’à :token requêtes par minute, et un compte jusqu’à :user avec tous ses jetons.',
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Choisis au moins une capacité.',
            'wildcard' => 'Un jeton ne peut pas porter toutes les capacités à la fois.',
            'unknown' => 'Une des capacités n’est pas proposée.',
            'reserved' => 'Une des capacités n’est pas proposée ici.',
            'malformed' => 'Une des capacités n’est pas proposée.',
            'not_tokenable' => 'Aucun jeton ne peut être créé pour ce compte.',
            'password_not_confirmed' => 'Confirme ton mot de passe avant de créer un jeton.',
            'too_fast' => 'Les jetons sont créés trop vite. Réessaie dans un instant.',
            'limit_reached' => 'Tu as autant de jetons que permis. Révoques-en un d’abord.',
            'lifetime' => 'Cette durée n’est pas proposée.',
            'name' => 'Donne au jeton un nom de 255 caractères au plus.',
        ],
    ],
];
