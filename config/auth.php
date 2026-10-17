<?php

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Default guard is customer. Passwords broker is emptied as no password
    | reset broker is configured for multiple separate user tables.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'customer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Separate session guards for each user type, keeping login portals
    | and identities cleanly isolated.
    |
    */

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        'agent' => [
            'driver' => 'session',
            'provider' => 'agents',
        ],

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Each guard retrieves users via its own Eloquent model.
    |
    */

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],

        'agents' => [
            'driver' => 'eloquent',
            'model' => Agent::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Removed the default 'users' provider reference.
    |
    */

    'passwords' => [],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
