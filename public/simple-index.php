<?php

/**
 * Simple Bootstrap für Brick Framework
 */

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Environment
$_ENV['APP_ENV'] = 'development';
$_ENV['APP_DEBUG'] = true;

// Einfacher Router
class SimpleRouter 
{
    private $routes = [];
    
    public function __construct() 
    {
        $this->routes = require __DIR__ . '/../config/simple-routes.php';
    }
    
    public function handle($method, $uri): string 
    {
        $route = $method . ' ' . $uri;
        
        if (isset($this->routes[$route])) {
            $handler = $this->routes[$route];
            
            if (is_callable($handler)) {
                return $handler();
            }
            
            if (is_string($handler) && str_contains($handler, '@')) {
                [$controllerName, $methodName] = explode('@', $handler);
                $controllerClass = "App\\Controllers\\{$controllerName}";
                
                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $methodName)) {
                        return $controller->$methodName();
                    }
                }
            }
        }
        
        // 404
        return $this->notFound();
    }
    
    private function notFound(): string 
    {
        http_response_code(404);
        return '<!DOCTYPE html>
<html>
<head>
    <title>404 - Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="text-center">
        <h1 class="text-6xl font-bold text-gray-300">404</h1>
        <p class="text-xl text-gray-600 mt-4">Page not found</p>
        <a href="/" class="mt-6 inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Go Home</a>
    </div>
</body>
</html>';
    }
}

// Router initialisieren und Request verarbeiten
$router = new SimpleRouter();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

echo $router->handle($method, $uri);