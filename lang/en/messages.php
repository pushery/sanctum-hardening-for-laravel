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
        'never' => 'Never',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Quickstart',
        'quickstart_endpoint' => 'Send a request with the token to',
        'quickstart_limits' => 'Each token makes up to :token requests a minute, and an account up to :user over all of its tokens.',
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Choose at least one ability.',
            'wildcard' => 'A token cannot carry every ability at once.',
            'unknown' => 'One of the abilities is not offered.',
            'reserved' => 'One of the abilities is not offered here.',
            'malformed' => 'One of the abilities is not offered.',
            'not_tokenable' => 'Tokens cannot be created for this account.',
            'password_not_confirmed' => 'Confirm your password before you create a token.',
            'too_fast' => 'Tokens are being created too fast. Try again in a moment.',
            'limit_reached' => 'You hold as many tokens as allowed. Revoke one first.',
            'lifetime' => 'This lifetime is not offered.',
            'name' => 'Give the token a name of at most 255 characters.',
        ],
    ],
];
