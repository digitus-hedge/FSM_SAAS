<?php

/*
 | Daily exchange rates (CUR-01, Currency-and-Payments spec 6.1).
 |
 | The provider is swappable: set RATES_PROVIDER to a key of 'providers' below, or add a class that
 | implements App\Services\Currency\RateProvider and register it here. Nothing else changes.
 */
return [

    'provider' => env('RATES_PROVIDER', 'open_er_api'),

    'providers' => [
        'open_er_api' => App\Services\Currency\ExchangeRateApiProvider::class,
        'fake'        => App\Services\Currency\FakeRateProvider::class,
    ],

    /*
     | open.er-api.com (the free "open access" endpoint of ExchangeRate-API).
     |
     | TERMS TO KEEP (checked against https://www.exchangerate-api.com/docs/free on 19 Nov 2026, re-check before go-live):
     |  - Attribution is REQUIRED. Any page or document that shows these rates must link to https://www.exchangerate-api.com
     |    with a text such as "Rates By Exchange Rate API". The Currencies and rates page already shows it.
     |  - The data refreshes once every 24 hours. Do not call it more often than needed: we call it once per base
     |    currency per run and cache the answer, and retry only pairs that are still missing.
     |  - Over-use is rate limited (HTTP 429, then a temporary block). The retries in routes/console.php are spaced hours apart.
     |  - No API key and no SLA. If the company needs guaranteed uptime or commercial use at volume, switch to a paid
     |    provider behind the same RateProvider interface.
     */
    'open_er_api' => [
        'base_url'    => env('RATES_OPEN_ER_API_URL', 'https://open.er-api.com/v6/latest'),
        'timeout'     => 15,
        'attribution' => [
            'text' => 'Rates By Exchange Rate API',
            'url'  => 'https://www.exchangerate-api.com',
        ],
    ],

    // How long the list of provider-supported currencies is cached (seconds).
    'supported_cache_seconds' => 86400,

    // Used for the Add currency list when the provider cannot be reached.
    'fallback_currencies' => [
        'AED', 'AUD', 'BHD', 'CAD', 'CHF', 'CNY', 'EUR', 'GBP', 'HKD', 'INR', 'JPY', 'KWD',
        'LKR', 'MYR', 'NZD', 'OMR', 'PHP', 'PKR', 'QAR', 'SAR', 'SGD', 'THB', 'USD', 'ZAR',
    ],
];
