<?php
/**
 * Database Configuration für Brick Framework
 */

return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'default',
    
    'connections' => [
        'default' => [
            'driver'   => $_ENV['DB_DRIVER'] ?? 'sqlite',
            'host'     => $_ENV['DB_HOST'] ?? 'localhost',
            'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
            'dbname'   => $_ENV['DB_DATABASE'] ?? 'brick',
            'user'     => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'path'     => $_ENV['DB_PATH'] ?? __DIR__ . '/../storage/database.sqlite',
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 30,
            ],
        ],
        
        'testing' => [
            'driver'  => 'sqlite',
            'path'    => $_ENV['DB_TESTING_PATH'] ?? __DIR__ . '/../storage/database/testing.sqlite',
            'options' => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10,
            ],
        ],
    ],
    
    'log_queries' => filter_var($_ENV['DB_LOG_QUERIES'] ?? false, FILTER_VALIDATE_BOOLEAN),
];