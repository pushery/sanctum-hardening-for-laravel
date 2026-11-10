<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Too many requests. Another one can be sent once the time in the Retry-After header has passed.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'API tokens',
        'intro' => 'A token can do what its abilities name and never more than your account.',
        'name' => 'Name',
        'abilities' => 'Abilities',
        'lifetime' => 'Lifetime',
        'days' => ':count day|:count days',
        'no_expiry' => 'No expiry',
        'mint' => 'Create token',
        'copy_now' => 'Copy the token now. It is shown only this once.',
        'minted' => 'The token was created.',
        'revoke' => 'Revoke',
        'revoke_named' => 'Revoke :name',
        'confirm_revoke' => 'Revoke this token? Requests that use it stop working.',
        'revoked' => 'The token was revoked.',
        'not_revoked' => 'No token was revoked.',
        'none' => 'No tokens yet.',
        'prefix' => 'Prefix',
        'expires' => 'Expires',
        'last_used' => 'Last used',
        'recent' => 'Recent requests',
        'never' => 'Never',
        'networks' => 'Networks',
        'networks_hint' => 'Optional. One address or network a line, such as 203.0.113.7 or 10.0.0.0/8. The token then works from these alone.',
        'only_from' => 'Only from :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Quickstart',
        'quickstart_endpoint' => 'Send a request with the token to',
        'quickstart_limits' => 'Each token makes up to :token requests a minute, and an account up to :user over all of its tokens.',
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Confirm your password before you create a token.',
        'password' => 'Password',
        'confirm' => 'Confirm password',
        'confirmed' => 'Your password is confirmed.',
        'not_confirmed' => 'That is not your password.',
        'confirm_too_fast' => 'Too many wrong passwords. Try again in a minute.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'A token can do what its abilities name and never more than the holder it belongs to.',
            'none' => 'No tokens yet.',
            'quickstart_limits' => 'Each token makes up to :token requests a minute, and the tokens of this holder up to :user together.',
            'refused' => [
                'not_tokenable' => 'Tokens cannot be created for this holder.',
                'limit_reached' => 'This holder has as many tokens as allowed. Revoke one first.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Choose at least one ability.',
            'wildcard' => 'A token cannot carry every ability at once.',
            'unknown' => 'One of the abilities is not offered.',
            'reserved' => 'One of the abilities is not offered here.',
            'malformed' => 'One of the abilities is not offered.',
            'not_tokenable' => 'Tokens cannot be created for this account.',
            'not_allowed' => 'You may not manage the tokens of this holder.',
            'password_not_confirmed' => 'Confirm your password before you create a token.',
            'too_fast' => 'Tokens are being created too fast. Try again in a moment.',
            'limit_reached' => 'You hold as many tokens as allowed. Revoke one first.',
            'lifetime' => 'This lifetime is not offered.',
            'name' => 'Give the token a name of at most 255 characters.',
            'network' => 'A token cannot be limited to one of these addresses or networks.',
        ],
    ],
];
