<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exchange rates
    |--------------------------------------------------------------------------
    |
    | Which provider converts between currencies. Rates are only fetched the
    | first time a currency conversion happens, then cached.
    |
    | Supported: "ecb", "frankfurter", "exchangerate-api", "openexchangerates",
    |            "fixed", null (currency conversion disabled), or the class name
    |            of any Unitary\Currency\ExchangeRateProvider in the container.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Region
    |--------------------------------------------------------------------------
    |
    | The region @quantity(..., usage: 'road') picks units for, e.g. "GB" for
    | miles on roads and stones for people's weight. When this is null, the
    | region comes from the app locale ("en_GB"), and a locale without one
    | ("en") gets CLDR's worldwide defaults.
    |
    */

    'region' => env('UNITARY_REGION'),

    /*
    |--------------------------------------------------------------------------
    | Input locale
    |--------------------------------------------------------------------------
    |
    | The locale people type quantities in, for $request->quantity() and the
    | quantity validation rule. In German, "1,5 kg" is one and a half
    | kilograms and "5 Fuß 10 Zoll" is 70 inches. When this is null, the app
    | locale is used. Set it to "en" to read input the English way. Values
    | set in code, like casts, are always read the English way.
    |
    */

    'locale' => env('UNITARY_LOCALE'),

    'exchange_rates' => [
        'provider' => env('UNITARY_EXCHANGE_RATES', 'ecb'),

        // Seconds to cache a fetched rate table for.
        'ttl' => (int) env('UNITARY_EXCHANGE_RATES_TTL', 3600),

        // Cache store for rate tables: null for the default store, false to disable.
        'cache_store' => env('UNITARY_CACHE_STORE'),

        'frankfurter' => [
            'url' => env('FRANKFURTER_URL', 'https://api.frankfurter.dev/v1'),
        ],

        'exchangerate-api' => [
            // Optional: without a key the free open-access endpoint is used.
            'key' => env('EXCHANGERATE_API_KEY'),
        ],

        'openexchangerates' => [
            'app_id' => env('OPENEXCHANGERATES_APP_ID'),
        ],

        'fixed' => [
            'base' => 'USD',
            // ISO code => units per one base currency, e.g. 'GBP' => '0.79'.
            'rates' => [],
        ],
    ],

];
