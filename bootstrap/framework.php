<?php
/**
 * Brick Framework Bootstrap
 * 
 * Lädt und initialisiert alle Core-Komponenten des Frameworks
 * Stellt Service Container und Auto-Wiring bereit
 */

declare(strict_types=1);

// Framework-Konstanten definieren
if (!defined('FRAMEWORK_START')) {
    define('FRAMEWORK_START', microtime(true));
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Composer Autoloader
$autoloader = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    // Fallback: Eigener Autoloader für Core-Klassen
    spl_autoload_register(function ($class) {
        if (str_starts_with($class, 'Core\\')) {
            $file = ROOT_PATH . '/core/' . str_replace('Core\\', '', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        if (str_starts_with($class, 'App\\')) {
            $file = ROOT_PATH . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
} else {
    require_once $autoloader;
}

// Route Helper Functions laden
require_once __DIR__ . '/route-helpers.php';

use Core\Database;
use Core\Request;
use Core\Router;
use Core\View;
use Core\Container; 



/**
 * Framework Application Klasse
 */
class App
{
    private Container $container;
    private bool $booted = false;

    public function __construct()
    {
        $this->container = new Container();
        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        // Database Service
        $this->container->singleton('database', function () {
            $config = $this->loadConfig('database');
            
            if (isset($config['connections'])) {
                foreach ($config['connections'] as $name => $conn) {
                    Database::addConnection($name, $conn);
                }
                Database::setDefaultConnection($config['default'] ?? 'default');
            } else {
                Database::addConnection('default', $config);
            }
            
            Database::enableQueryLog($config['log_queries'] ?? false);
            
            return new Database();
        });

        // Request Service
        $this->container->bind('request', function () {
            return Request::fromGlobals();
        });

        // Router Service
        $this->container->singleton('router', function () {
            return new Router();
        });

        // View Service (DISABLED due to memory issues)
        $this->container->singleton('view', function () {
            // Disable complex View system to prevent memory issues
            // View::setBasePath(ROOT_PATH . '/app/Views');
            // View::setCachePath(ROOT_PATH . '/storage/cache/views');
            // View::cache(($_ENV['APP_ENV'] ?? 'development') === 'production');
            
            // Return a simple placeholder instead
            return new \stdClass();
        });
    }

    public function get(string $service)
    {
        return $this->container->get($service);
    }

    public function boot(): void
    {
        if ($this->booted) return;
        
        // .env Datei laden falls vorhanden
        $this->loadEnvironmentVariables();
        
        // Konfiguration laden
        $this->loadAppConfig();
        
        // Services initialisieren
        $this->get('database');
        $this->get('view');
        
        $this->booted = true;
    }

    public function run(): void
    {
        $this->boot();
        
        try {
            $request = $this->get('request');
            $router = $this->get('router');
            
            // Routes laden
            $this->loadRoutes($router);
            
            // Request dispatchen
            $response = $router->dispatch($request);
            
            // Response senden
            $response->send();
            
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    private function loadRoutes(Router $router): void
    {
        $routesFile = ROOT_PATH . '/config/routes.php';
        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            if (is_callable($routes)) {
                $routes($router, $this);
            }
        }
    }

    private function loadConfig(string $name): array
    {
        $file = ROOT_PATH . "/config/{$name}.php";
        if (!file_exists($file)) {
            throw new \RuntimeException("Config file not found: {$name}");
        }
        return require $file;
    }

    private function loadAppConfig(): void
    {
        $config = $this->loadConfig('app');
        
        // Timezone setzen
        if (isset($config['timezone'])) {
            date_default_timezone_set($config['timezone']);
        }
        
        // Error Reporting
        if (isset($config['debug'])) {
            ini_set('display_errors', $config['debug'] ? '1' : '0');
            error_reporting($config['debug'] ? E_ALL : 0);
        }
    }

    private function loadEnvironmentVariables(): void
    {
        $envFile = ROOT_PATH . '/.env';
        if (!file_exists($envFile)) return;
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"' \t\n\r");
            
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    private function handleError(\Throwable $e): void
    {
        http_response_code(500);
        
        if (($_ENV['APP_DEBUG'] ?? false) || ($_ENV['APP_ENV'] ?? '') === 'development') {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            echo "Internal Server Error";
        }
        
        exit(1);
    }
}

$app = new App();

$app->run();