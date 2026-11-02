<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    'seo_pages' => [
        'football.home' => [
            'title' => 'BF Markets Analytics | Sports Exchange Market Data & Analytics',
            'description' => 'Real-time sports exchange market data and analytics. Monitor odds movements, liquidity, market activity, momentum, volatility and automated alerts with BF Markets Analytics.',
        ],
        'football.market-activity' => [
            'title' => 'Sports Market Activity & Liquidity | BF Markets Analytics',
            'description' => 'Monitor real-time sports exchange market activity, Back/Lay prices, liquidity, momentum, volatility and price movements with BF Markets Analytics.',
            'index' => false,
        ],
        'football.pinnacle-odds' => [
            'title' => 'Pinnacle Odds Drops & Market Movement | BF Markets',
            'description' => 'Track Pinnacle odds drops and price movements in real time, with market data, NVP and exchange comparisons from BF Markets Analytics.',
            'index' => false,
        ],
        'notifications' => [
            'title' => 'Sports Market Alerts & Telegram Notifications | BF Markets',
            'description' => 'Receive automated sports market alerts for odds movements, market shocks, favourite losses and other market signals through Telegram.',
            'index' => false,
        ],
        'faq' => [
            'description' => 'Learn about BF Markets Analytics, sports exchange market data, available markets, platform access and alerts.',
            'faq' => [
                'How quickly is the data updated?' => 'BF Markets uses high-speed infrastructure so that market changes, odds movements, and liquidity updates are delivered to users in real time.',
            ],
        ],
        'manifesto' => ['description' => 'Read the BF Markets manifesto and the principles behind its sports exchange market analytics platform.'],
        'filter-guide' => ['description' => 'Understand the existing BF Markets filters for live fixtures, favourites, prematch drops and market shocks.'],
        'market-activity-guide' => ['description' => 'Learn how to read Back/Lay prices, balance, momentum, delta, volatility and liquidity in BF Markets.'],
        'pinnacle-odds-drops-guide' => ['description' => 'Learn about Pinnacle odds drops, NVP, fair prices and comparisons with exchange market data.'],
        'contact-us' => ['description' => 'Contact BF Markets Analytics for help with the platform, accounts and sports exchange market monitoring.'],
        'privacy-policy' => ['description' => 'Read how BF Markets handles personal information, account data and privacy requests.'],
        'terms-of-service' => ['description' => 'Read the terms that govern access to and use of the BF Markets platform.'],
        'disclaimer' => ['description' => 'Read the BF Markets disclaimer and the limitations of sports market data and analytics.'],
        'refund-policy' => ['description' => 'Read the refund conditions and request process for BF Markets subscriptions.'],
    ],

    'branding_assets' => [
        'logo_en_light' => 'Logo — English — Light Mode',
        'logo_en_dark' => 'Logo — English — Dark Mode',
        'logo_ka_light' => 'Logo — Georgian — Light Mode',
        'logo_ka_dark' => 'Logo — Georgian — Dark Mode',
        'favicon_en_light' => 'Favicon — English — Light Mode',
        'favicon_en_dark' => 'Favicon — English — Dark Mode',
        'favicon_ka_light' => 'Favicon — Georgian — Light Mode',
        'favicon_ka_dark' => 'Favicon — Georgian — Dark Mode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'supported_locales' => ['en' => 'EN', 'ka' => 'KA'],

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
