<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Demasiadas solicitudes. Se puede enviar otra cuando haya pasado el tiempo de la cabecera Retry-After.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'Tokens de API',
        'intro' => 'Un token puede lo que nombran sus capacidades y nunca más que tu cuenta.',
        'name' => 'Nombre',
        'abilities' => 'Capacidades',
        'lifetime' => 'Duración',
        'days' => ':count día|:count días',
        'no_expiry' => 'Sin caducidad',
        'mint' => 'Crear token',
        'copy_now' => 'Copia el token ahora. Solo se muestra esta vez.',
        'minted' => 'Se ha creado el token.',
        'revoke' => 'Revocar',
        'revoke_named' => 'Revocar :name',
        'confirm_revoke' => '¿Revocar este token? Las solicitudes que lo usan dejarán de funcionar.',
        'revoked' => 'Se ha revocado el token.',
        'not_revoked' => 'No se ha revocado ningún token.',
        'none' => 'Todavía no hay tokens.',
        'prefix' => 'Prefijo',
        'expires' => 'Caduca',
        'last_used' => 'Último uso',
        'recent' => 'Solicitudes recientes',
        'never' => 'Nunca',
        'networks' => 'Redes',
        'networks_hint' => 'Opcional. Una dirección o una red por línea, como 203.0.113.7 o 10.0.0.0/8. El token solo funciona entonces desde ahí.',
        'only_from' => 'Solo desde :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Inicio rápido',
        'quickstart_endpoint' => 'Envía una solicitud con el token a',
        'quickstart_limits' => 'Cada token hace hasta :token solicitudes por minuto, y una cuenta hasta :user con todos sus tokens.',
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Confirma tu contraseña antes de crear un token.',
        'password' => 'Contraseña',
        'confirm' => 'Confirmar contraseña',
        'confirmed' => 'Tu contraseña está confirmada.',
        'not_confirmed' => 'Esa no es tu contraseña.',
        'confirm_too_fast' => 'Demasiadas contraseñas incorrectas. Vuelve a intentarlo en un minuto.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Un token puede lo que nombran sus capacidades y nunca más que el titular al que pertenece.',
            'none' => 'Todavía no hay tokens.',
            'quickstart_limits' => 'Cada token hace hasta :token solicitudes por minuto, y los tokens de este titular hasta :user en conjunto.',
            'refused' => [
                'not_tokenable' => 'No se pueden crear tokens para este titular.',
                'limit_reached' => 'Este titular tiene tantos tokens como se permite. Revoca uno primero.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Elige al menos una capacidad.',
            'wildcard' => 'Un token no puede llevar todas las capacidades a la vez.',
            'unknown' => 'Una de las capacidades no se ofrece.',
            'reserved' => 'Una de las capacidades no se ofrece aquí.',
            'malformed' => 'Una de las capacidades no se ofrece.',
            'not_tokenable' => 'No se pueden crear tokens para esta cuenta.',
            'not_allowed' => 'No puedes gestionar los tokens de este titular.',
            'password_not_confirmed' => 'Confirma tu contraseña antes de crear un token.',
            'too_fast' => 'Se están creando tokens demasiado rápido. Vuelve a intentarlo en un momento.',
            'limit_reached' => 'Tienes tantos tokens como se permite. Revoca uno primero.',
            'lifetime' => 'Esta duración no se ofrece.',
            'name' => 'Dale al token un nombre de 255 caracteres como máximo.',
            'network' => 'Una de las direcciones o redes no sirve para limitar un token.',
        ],
    ],
];
