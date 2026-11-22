<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Demasiadas solicitudes. Se puede enviar otra cuando haya pasado el tiempo de la cabecera Retry-After.',
    // The detail of the problem document a write is answered with where the tier allows no writes.
    'writes_refused' => 'Este nivel no permite solicitudes que escriban o eliminen.',
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
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Token',
        'minted' => 'Se ha creado el token.',
        'revoke' => 'Revocar',
        'revoke_named' => 'Revocar :name',
        'confirm_revoke' => '¿Revocar este token? Las solicitudes que lo usan dejarán de funcionar.',
        'revoked' => 'Se ha revocado el token.',
        'not_revoked' => 'No se ha revocado ningún token.',
        'rotate' => 'Renovar',
        'rotate_named' => 'Renovar :name',
        'confirm_rotate' => '¿Sustituir este token por uno nuevo? Seguirá funcionando :overlap para que lo que lo usa pueda pasar al nuevo.',
        'overlap' => 'Tras renovarlo, el token antiguo sigue funcionando durante',
        'hours' => ':count hora|:count horas',
        'rotated' => 'Un token nuevo lo sustituye. El antiguo sigue funcionando hasta la hora indicada a su lado.',
        'not_rotated' => 'No se ha sustituido ningún token.',
        'replaced_until' => 'Sustituido, funciona hasta :date',
        'none' => 'Todavía no hay tokens.',
        'prefix' => 'Prefijo',
        'expires' => 'Caduca',
        'last_used' => 'Último uso',
        'recent' => 'Solicitudes recientes',
        'never' => 'Nunca',
        'not_recorded' => 'Sin registrar',
        'networks' => 'Redes',
        'networks_hint' => 'Opcional. Una dirección o una red por línea, como 203.0.113.7 o 10.0.0.0/8. El token solo funciona entonces desde ahí.',
        'only_from' => 'Solo desde :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT',
        ],
        'quickstart' => 'Inicio rápido',
        'quickstart_endpoint' => 'Envía una solicitud con el token a',
        'quickstart_limits' => 'Cada token hace hasta :token solicitudes por minuto, y una cuenta hasta :user con todos sus tokens.',
        'quickstart_token_limit' => 'Cada token hace hasta :token solicitudes por minuto.',
        'quickstart_user_limit' => 'Una cuenta hace hasta :user solicitudes por minuto con todos sus tokens.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'Nuevo token',
            'mint_description' => 'Un token con las capacidades, la duración y las redes que elijas.',
            'tokens' => 'Tokens',
            'tokens_description' => 'Cada token, el más reciente primero, con sus capacidades, su caducidad y su último uso.',
            'quickstart_description' => 'Adónde envía un programa sus solicitudes con un token, y cuántas puede enviar.',
        ],
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
            'quickstart_user_limit' => 'Los tokens de este titular hacen hasta :user solicitudes por minuto en conjunto.',
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
            'holder' => 'Una de las capacidades no se ofrece para este titular.',
            'malformed' => 'Una de las capacidades no se ofrece.',
            'not_tokenable' => 'No se pueden crear tokens para esta cuenta.',
            'not_allowed' => 'No puedes gestionar los tokens de este titular.',
            'password_not_confirmed' => 'Confirma tu contraseña antes de crear un token.',
            'too_fast' => 'Se están creando tokens demasiado rápido. Vuelve a intentarlo en un momento.',
            'limit_reached' => 'Tienes tantos tokens como se permite. Revoca uno primero.',
            'lifetime' => 'Esta duración no se ofrece.',
            'overlap' => 'Elige de la lista cuánto tiempo sigue funcionando el token antiguo.',
            'name' => 'Dale al token un nombre de 255 caracteres como máximo.',
            'network' => 'Un token no se puede limitar a «:network»: no es una dirección ni una red.',
            'too_many_networks' => 'Un token se puede limitar a :limit direcciones y redes como máximo.',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'El token empieza por :prefix.',
        'revoke' => 'Si ya no lo necesitas, revócalo en la página de tus tokens de API.',
        'open' => 'Abrir tus tokens de API',
        'expiring' => [
            'subject' => 'Tu token de API «:name» caduca el :date',
            'line' => 'Tu token de API «:name» caduca el :date. Las solicitudes que lo usan dejarán de funcionar entonces, así que crea uno nuevo antes si aún lo necesitas.',
        ],
        'inactive' => [
            'subject_used' => 'Tu token de API «:name» no se usa desde el :date',
            'line_used' => 'Tu token de API «:name» se usó por última vez el :date.',
            'subject_never' => 'Tu token de API «:name» nunca se ha usado',
            'line_never' => 'Tu token de API «:name» se creó el :date y nunca se ha usado.',
            'risk' => 'Un token que nadie usa sigue funcionando para quien lo tenga.',
        ],
    ],
];
