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
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Jeton',
        'minted' => 'Le jeton a été créé.',
        'revoke' => 'Révoquer',
        'revoke_named' => 'Révoquer :name',
        'confirm_revoke' => 'Révoquer ce jeton ? Les requêtes qui l’utilisent cesseront de fonctionner.',
        'revoked' => 'Le jeton a été révoqué.',
        'not_revoked' => 'Aucun jeton n’a été révoqué.',
        'rotate' => 'Renouveler',
        'rotate_named' => 'Renouveler :name',
        'confirm_rotate' => 'Remplacer ce jeton par un nouveau ? Il fonctionne encore :overlap, le temps que ce qui l’utilise passe au nouveau.',
        'overlap' => 'Après le renouvellement, l’ancien jeton fonctionne encore',
        'hours' => ':count heure|:count heures',
        'rotated' => 'Un nouveau jeton le remplace. L’ancien fonctionne jusqu’à l’heure indiquée à côté.',
        'not_rotated' => 'Aucun jeton n’a été remplacé.',
        'replaced_until' => 'Remplacé, fonctionne jusqu’au :date',
        'none' => 'Aucun jeton pour l’instant.',
        'prefix' => 'Préfixe',
        'expires' => 'Expire',
        'last_used' => 'Dernière utilisation',
        'recent' => 'Requêtes récentes',
        'never' => 'Jamais',
        'not_recorded' => 'Non enregistré',
        'networks' => 'Réseaux',
        'networks_hint' => 'Facultatif. Une adresse ou un réseau par ligne, par exemple 203.0.113.7 ou 10.0.0.0/8. Le jeton ne fonctionne alors que depuis ceux-ci.',
        'only_from' => 'Uniquement depuis :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT',
        ],
        'quickstart' => 'Démarrage rapide',
        'quickstart_endpoint' => 'Envoie une requête avec le jeton à',
        'quickstart_limits' => 'Chaque jeton fait jusqu’à :token requêtes par minute, et un compte jusqu’à :user avec tous ses jetons.',
        'quickstart_token_limit' => 'Chaque jeton fait jusqu’à :token requêtes par minute.',
        'quickstart_user_limit' => 'Un compte fait jusqu’à :user requêtes par minute avec tous ses jetons.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'Nouveau jeton',
            'mint_description' => 'Un jeton avec les capacités, la durée et les réseaux que tu choisis.',
            'tokens' => 'Jetons',
            'tokens_description' => 'Chaque jeton, le plus récent d’abord, avec ses capacités, son expiration et sa dernière utilisation.',
            'quickstart_description' => 'Où un programme envoie ses requêtes avec un jeton, et combien il peut en envoyer.',
        ],
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Confirme ton mot de passe avant de créer un jeton.',
        'password' => 'Mot de passe',
        'confirm' => 'Confirmer le mot de passe',
        'confirmed' => 'Ton mot de passe est confirmé.',
        'not_confirmed' => 'Ce n’est pas ton mot de passe.',
        'confirm_too_fast' => 'Trop de mots de passe erronés. Réessaie dans une minute.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Un jeton peut ce que nomment ses capacités, et jamais plus que le titulaire auquel il appartient.',
            'none' => 'Aucun jeton pour l’instant.',
            'quickstart_limits' => 'Chaque jeton fait jusqu’à :token requêtes par minute, et les jetons de ce titulaire jusqu’à :user ensemble.',
            'quickstart_user_limit' => 'Les jetons de ce titulaire font jusqu’à :user requêtes par minute ensemble.',
            'refused' => [
                'not_tokenable' => 'Aucun jeton ne peut être créé pour ce titulaire.',
                'limit_reached' => 'Ce titulaire a autant de jetons que permis. Révoques-en un d’abord.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Choisis au moins une capacité.',
            'wildcard' => 'Un jeton ne peut pas porter toutes les capacités à la fois.',
            'unknown' => 'Une des capacités n’est pas proposée.',
            'reserved' => 'Une des capacités n’est pas proposée ici.',
            'holder' => 'Une des capacités n’est pas proposée pour ce titulaire.',
            'malformed' => 'Une des capacités n’est pas proposée.',
            'not_tokenable' => 'Aucun jeton ne peut être créé pour ce compte.',
            'not_allowed' => 'Tu ne peux pas gérer les jetons de ce titulaire.',
            'password_not_confirmed' => 'Confirme ton mot de passe avant de créer un jeton.',
            'too_fast' => 'Les jetons sont créés trop vite. Réessaie dans un instant.',
            'limit_reached' => 'Tu as autant de jetons que permis. Révoques-en un d’abord.',
            'lifetime' => 'Cette durée n’est pas proposée.',
            'overlap' => 'Choisis dans la liste combien de temps l’ancien jeton fonctionne encore.',
            'name' => 'Donne au jeton un nom de 255 caractères au plus.',
            'network' => 'Un jeton ne peut pas être limité à « :network » : ce n’est ni une adresse ni un réseau.',
            'too_many_networks' => 'Un jeton peut être limité à :limit adresses et réseaux au plus.',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'Le jeton commence par :prefix.',
        'revoke' => 'Si tu n’en as plus besoin, révoque-le sur la page de tes jetons d’API.',
        'open' => 'Ouvrir tes jetons d’API',
        'expiring' => [
            'subject' => 'Ton jeton d’API « :name » expire le :date',
            'line' => 'Ton jeton d’API « :name » expire le :date. Les requêtes qui l’utilisent cesseront alors de fonctionner : crée-en un nouveau avant si tu en as encore besoin.',
        ],
        'inactive' => [
            'subject_used' => 'Ton jeton d’API « :name » n’a pas été utilisé depuis le :date',
            'line_used' => 'Ton jeton d’API « :name » a été utilisé pour la dernière fois le :date.',
            'subject_never' => 'Ton jeton d’API « :name » n’a jamais été utilisé',
            'line_never' => 'Ton jeton d’API « :name » a été créé le :date et n’a jamais été utilisé.',
            'risk' => 'Un jeton que personne n’utilise fonctionne toujours pour quiconque le détient.',
        ],
    ],
];
