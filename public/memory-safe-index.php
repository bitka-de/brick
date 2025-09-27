<?php

/**
 * Simple Bootstrap - Memory-Safe Version
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Environment
$_ENV['APP_ENV'] = 'development';
$_ENV['APP_DEBUG'] = true;

// Simple Request Handler
function handleRequest(): string 
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    
    // Routes
    switch ($uri) {
        case '/':
            return renderHome();
        case '/dashboard':
            return renderDashboard();
        case '/about':
            return renderAbout();
        case '/api/health':
            header('Content-Type: application/json');
            return json_encode([
                'status' => 'healthy',
                'timestamp' => time(),
                'version' => '1.0.0',
                'memory' => memory_get_usage(true)
            ]);
        default:
            return render404();
    }
}

function renderHome(): string 
{
    $data = [
        'title' => 'Welcome to Brick Framework',
        'message' => 'Your framework is running successfully!',
        'version' => '1.0.0',
        'environment' => 'development'
    ];
    
    return renderTemplate('home', $data);
}

function renderDashboard(): string 
{
    $data = [
        'title' => 'Dashboard',
        'stats' => [
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB',
            'php' => PHP_VERSION,
            'time' => date('H:i:s')
        ]
    ];
    
    return renderTemplate('dashboard', $data);
}

function renderAbout(): string 
{
    return renderTemplate('about', ['title' => 'About Brick Framework']);
}

function render404(): string 
{
    http_response_code(404);
    return renderTemplate('404', ['title' => '404 - Not Found']);
}

function renderTemplate(string $template, array $data = []): string 
{
    extract($data);
    
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Brick Framework') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <h1 class="text-xl font-bold text-gray-800">
                    <a href="/">Brick Framework</a>
                </h1>
                <div class="flex space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">Home</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">Dashboard</a>
                    <a href="/about" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">About</a>
                    <a href="/api/health" class="text-blue-600 hover:text-blue-900 px-3 py-2 rounded-md">API</a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="max-w-7xl mx-auto py-6 px-4">
        <?php 
        // Template Content
        switch ($template) {
            case 'home':
                ?>
                <div class="gradient-bg text-white rounded-lg p-8 mb-8">
                    <div class="text-center">
                        <h1 class="text-4xl font-bold mb-4"><?= htmlspecialchars($title ?? 'Welcome') ?></h1>
                        <p class="text-xl opacity-90"><?= htmlspecialchars($message ?? 'Framework is running') ?></p>
                        <p class="mt-4 opacity-75">Version <?= htmlspecialchars($version ?? '1.0.0') ?> | Environment: <?= htmlspecialchars($environment ?? 'development') ?></p>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-2">🚀 Quick Start</h3>
                        <p class="text-gray-600 mb-4">Get started in minutes</p>
                        <a href="/dashboard" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Dashboard</a>
                    </div>
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-2">⚡ API Ready</h3>
                        <p class="text-gray-600 mb-4">RESTful APIs</p>
                        <a href="/api/health" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Test API</a>
                    </div>
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-2">📚 Learn More</h3>
                        <p class="text-gray-600 mb-4">Framework features</p>
                        <a href="/about" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">About</a>
                    </div>
                </div>
                <?php
                break;
                
            case 'dashboard':
                ?>
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h1 class="text-3xl font-bold mb-6"><?= htmlspecialchars($title ?? 'Dashboard') ?></h1>
                    
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="bg-blue-50 p-6 rounded-lg">
                            <h3 class="font-semibold mb-2">Memory Usage</h3>
                            <p class="text-2xl text-blue-600"><?= $stats['memory'] ?? 'N/A' ?></p>
                        </div>
                        <div class="bg-green-50 p-6 rounded-lg">
                            <h3 class="font-semibold mb-2">PHP Version</h3>
                            <p class="text-2xl text-green-600"><?= $stats['php'] ?? 'N/A' ?></p>
                        </div>
                        <div class="bg-purple-50 p-6 rounded-lg">
                            <h3 class="font-semibold mb-2">Server Time</h3>
                            <p class="text-2xl text-purple-600"><?= $stats['time'] ?? 'N/A' ?></p>
                        </div>
                    </div>
                </div>
                <?php
                break;
                
            case 'about':
                ?>
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h1 class="text-3xl font-bold mb-6">About Brick Framework</h1>
                    <p class="text-gray-600 mb-4">Brick Framework ist ein modernes PHP-Framework mit MVC-Architektur.</p>
                    <p class="text-gray-600">Built with ❤️ by JP Behrens.</p>
                </div>
                <?php
                break;
                
            case '404':
                ?>
                <div class="text-center">
                    <h1 class="text-6xl font-bold text-gray-300">404</h1>
                    <p class="text-xl text-gray-600 mt-4">Page not found</p>
                    <a href="/" class="mt-6 inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Go Home</a>
                </div>
                <?php
                break;
        }
        ?>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; <?= date('Y') ?> Brick Framework | Memory-Safe Edition</p>
        </div>
    </footer>
</body>
</html>
    <?php
    return ob_get_clean();
}

// Handle the request
echo handleRequest();