<?php
/**
 * Application Configuration
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'Brick Framework',
    
    'env' => $_ENV['APP_ENV'] ?? 'development',
    
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
    
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    
    'fallback_locale' => 'en',
    
    'key' => $_ENV['APP_KEY'] ?? 'base64:' . base64_encode(random_bytes(32)),
    
    'cipher' => 'AES-256-CBC',
    
    // Session Configuration
    'session' => [
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
        'lifetime' => $_ENV['SESSION_LIFETIME'] ?? 120, // minutes
        'expire_on_close' => false,
        'encrypt' => false,
        'files' => __DIR__ . '/../storage/sessions',
        'cookie' => $_ENV['SESSION_COOKIE'] ?? 'brick_session',
        'path' => '/',
        'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
        'secure' => $_ENV['SESSION_SECURE_COOKIE'] ?? false,
        'http_only' => true,
        'same_site' => 'Lax',
    ],
    
    // Logging Configuration
    'log' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path' => __DIR__ . '/../storage/logs/app.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/app.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
                'days' => 14,
            ],
        ],
    ],
    
    // Cache Configuration
    'cache' => [
        'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/cache',
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'cache',
                'connection' => null,
            ],
        ],
        'prefix' => $_ENV['CACHE_PREFIX'] ?? 'brick_cache',
    ],
    
    // View Configuration
    'view' => [
        'paths' => [
            __DIR__ . '/../app/Views',
        ],
        'compiled' => __DIR__ . '/../storage/cache/views',
    ],
    
    // Security Settings
    'security' => [
        'trusted_proxies' => explode(',', $_ENV['TRUSTED_PROXIES'] ?? ''),
        'trusted_hosts' => explode(',', $_ENV['TRUSTED_HOSTS'] ?? ''),
        'csrf_protection' => true,
        'cors' => [
            'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ],
    ],
];