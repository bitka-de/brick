<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * HomeController - Refactored für moderne Architektur
 * 
 * Behandelt alle Home/Landing-Page bezogenen Requests
 * Kompatibel mit sowohl einfachen als auch komplexen View-Systemen
 */
class HomeController extends Controller
{
  private const FRAMEWORK_VERSION = '1.0.0';
  private const DEFAULT_CACHE_TTL = 300; // 5 Minuten

  public function __construct(Request $request, Response $response)
  {
    parent::__construct($request, $response);
  }

  /**
   * Homepage/Welcome-Seite anzeigen
   */
  public function index(): Response
  {
    try {
      $data = $this->getHomePageData();

      if ($this->wantsJson()) {
        return $this->apiSuccess($data);
      }

      return $this->renderView('pages.home', $data);
    } catch (\Exception $e) {
      return $this->handleError($e, 'Error loading home page');
    }
  }

  /**
   * Alternative Welcome-Route für Kompatibilität
   */
  public function welcome(): Response
  {
    return $this->index();
  }

  /**
   * Dashboard mit System-Statistiken
   */
  public function dashboard(): Response
  {
    try {
      $data = [
        'title' => 'Dashboard - Brick Framework',
        'stats' => $this->getSystemStats(),
        'activities' => $this->getRecentActivities(),
        'environment_info' => $this->getEnvironmentInfo()
      ];

      if ($this->wantsJson()) {
        return $this->apiSuccess($data);
      }

      return $this->renderView('pages.dashboard', $data);
    } catch (\Exception $e) {
      return $this->handleError($e, 'Error loading dashboard');
    }
  }

  /**
   * API Health Check Endpoint
   */
  public function healthCheck(): Response
  {
    $healthData = [
      'status' => 'healthy',
      'timestamp' => time(),
      'version' => self::FRAMEWORK_VERSION,
      'environment' => $this->getEnvironment(),
      'memory' => [
        'current' => memory_get_usage(true),
        'current_formatted' => $this->formatBytes(memory_get_usage(true)),
        'peak' => memory_get_peak_usage(true),
        'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true))
      ],
      'system' => [
        'php_version' => PHP_VERSION,
        'server_time' => date('c'),
        'server_load' => $this->getServerLoad()
      ]
    ];

    return $this->apiSuccess($healthData);
  }

  /**
   * About-Seite mit Framework-Informationen
   */
  public function about(): Response
  {
    $data = [
      'title' => 'About Brick Framework',
      'framework_info' => [
        'name' => 'Brick Framework',
        'version' => self::FRAMEWORK_VERSION,
        'description' => 'Ein modernes PHP-Framework mit MVC-Architektur',
        'features' => $this->getFrameworkFeatures(),
        'author' => 'JP Behrens',
        'license' => 'MIT'
      ],
      'system_info' => $this->getSystemInfo()
    ];

    if ($this->wantsJson()) {
      return $this->apiSuccess($data);
    }

    return $this->renderView('pages.about', $data);
  }

  // ===== PRIVATE HELPER METHODS =====

  /**
   * Homepage-Daten sammeln
   */
  private function getHomePageData(): array
  {
    return [
      'title' => 'Welcome to Brick Framework',
      'message' => 'Your modern PHP framework is running successfully!',
      'version' => self::FRAMEWORK_VERSION,
      'environment' => $this->getEnvironment(),
      'features' => $this->getFrameworkFeatures(),
      'quick_stats' => [
        'memory_usage' => $this->formatBytes(memory_get_usage(true)),
        'php_version' => PHP_VERSION,
        'server_time' => date('H:i:s'),
        'framework_version' => self::FRAMEWORK_VERSION
      ]
    ];
  }

  /**
   * System-Statistiken für Dashboard
   */
  private function getSystemStats(): array
  {
    return [
      'memory' => [
        'current' => memory_get_usage(true),
        'current_formatted' => $this->formatBytes(memory_get_usage(true)),
        'peak' => memory_get_peak_usage(true),
        'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
        'limit' => ini_get('memory_limit')
      ],
      'php' => [
        'version' => PHP_VERSION,
        'extensions_count' => count(get_loaded_extensions()),
        'max_execution_time' => ini_get('max_execution_time')
      ],
      'server' => [
        'time' => date('H:i:s'),
        'date' => date('Y-m-d'),
        'timezone' => date_default_timezone_get(),
        'load' => $this->getServerLoad()
      ],
      'application' => [
        'framework_version' => self::FRAMEWORK_VERSION,
        'environment' => $this->getEnvironment(),
        'debug_mode' => $this->isDebugMode(),
        'uptime' => $this->getUptime()
      ]
    ];
  }

  /**
   * Aktuelle Aktivitäten (Mock-Daten)
   */
  private function getRecentActivities(): array
  {
    return [
      ['action' => 'Framework initialized', 'time' => 'Just now', 'user' => 'System', 'type' => 'info'],
      ['action' => 'User session started', 'time' => '2 minutes ago', 'user' => 'Developer', 'type' => 'success'],
      ['action' => 'Cache cleared', 'time' => '5 minutes ago', 'user' => 'Admin', 'type' => 'warning'],
      ['action' => 'Database connected', 'time' => '10 minutes ago', 'user' => 'System', 'type' => 'success'],
      ['action' => 'Configuration loaded', 'time' => '15 minutes ago', 'user' => 'System', 'type' => 'info']
    ];
  }

  /**
   * Framework-Features auflisten
   */
  private function getFrameworkFeatures(): array
  {
    return [
      'MVC Architecture' => 'Clean separation of concerns',
      'Dependency Injection' => 'Service Container für lose Kopplung',
      'RESTful Routing' => 'Moderne HTTP-Route-Definitionen',
      'Template Engine' => 'Blade-ähnliche Syntax mit Caching',
      'Database Layer' => 'ActiveRecord mit QueryBuilder',
      'Memory Optimized' => 'Effizienter Speicher-Umgang',
      'Error Handling' => 'Comprehensive error management',
      'API Ready' => 'JSON/XML Response-Support'
    ];
  }

  /**
   * Environment-Information
   */
  private function getEnvironmentInfo(): array
  {
    return [
      'app_env' => $this->getEnvironment(),
      'debug_mode' => $this->isDebugMode(),
      'timezone' => date_default_timezone_get(),
      'locale' => 'de_DE',
      'charset' => 'UTF-8'
    ];
  }

  /**
   * System-Information sammeln
   */
  private function getSystemInfo(): array
  {
    return [
      'php' => [
        'version' => PHP_VERSION,
        'sapi' => php_sapi_name(),
        'extensions' => get_loaded_extensions(),
        'ini_file' => php_ini_loaded_file()
      ],
      'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'hostname' => gethostname(),
        'os' => PHP_OS,
        'architecture' => php_uname('m')
      ]
    ];
  }

  /**
   * View rendern mit Memory-Safe Fallback
   */
  private function renderView(string $view, array $data): Response
  {
    // DISABLE complex View system to prevent memory issues
    // Always use simple template rendering
    return $this->renderSimpleTemplate($view, $data);
  }
  /**
   * Memory-Safe Template-Rendering als Fallback
   */
  private function renderSimpleTemplate(string $view, array $data): Response
  {
    // Memory-safe template rendering
    $content = $this->generateSimpleHTML($view, $data);
    return Response::html($content);
  }

  /**
   * Einfache HTML-Generierung ohne externe Template-Engine
   */
  private function generateSimpleHTML(string $view, array $data): string
  {
    switch ($view) {
      case 'pages.home':
        return $this->generateHomePage($data);
      case 'pages.dashboard':
        return $this->generateDashboard($data);
      case 'pages.about':
        return $this->generateAboutPage($data);
      default:
        return $this->generateGenericPage($view, $data);
    }
  }

  private function generateHomePage(array $data): string
  {
    $title = htmlspecialchars($data['title'] ?? 'Welcome to Brick Framework');
    $message = htmlspecialchars($data['message'] ?? 'Framework is running');
    $version = htmlspecialchars($data['version'] ?? '1.0.0');
    $environment = htmlspecialchars($data['environment'] ?? 'development');

    return "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <style>.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }</style>
</head>
<body class='bg-gray-50 min-h-screen'>
    <nav class='bg-white shadow-lg'>
        <div class='max-w-7xl mx-auto px-4'>
            <div class='flex justify-between items-center py-4'>
                <h1 class='text-xl font-bold text-gray-800'>
                    <a href='/'>Brick Framework</a>
                </h1>
                <div class='flex space-x-4'>
                    <a href='/' class='text-gray-600 hover:text-gray-900 px-3 py-2'>Home</a>
                    <a href='/dashboard' class='text-gray-600 hover:text-gray-900 px-3 py-2'>Dashboard</a>
                    <a href='/about' class='text-gray-600 hover:text-gray-900 px-3 py-2'>About</a>
                    <a href='/api/health' class='text-blue-600 hover:text-blue-900 px-3 py-2'>API</a>
                </div>
            </div>
        </div>
    </nav>
    
    <main class='max-w-7xl mx-auto py-6 px-4'>
        <div class='gradient-bg text-white rounded-lg p-8 mb-8'>
            <div class='text-center'>
                <h1 class='text-4xl font-bold mb-4'>{$title}</h1>
                <p class='text-xl opacity-90'>{$message}</p>
                <p class='mt-4 opacity-75'>Version {$version} | Environment: {$environment}</p>
                <div class='mt-4 bg-white bg-opacity-20 rounded p-2 inline-block'>
                    <span class='text-sm'>🚀 Memory-Safe Template System</span>
                </div>
            </div>
        </div>
        
        <div class='grid md:grid-cols-3 gap-6'>
            <div class='bg-white rounded-lg shadow-lg p-6'>
                <h3 class='text-xl font-semibold mb-2'>🚀 Quick Start</h3>
                <p class='text-gray-600 mb-4'>Get started in minutes</p>
                <a href='/dashboard' class='bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700'>Dashboard</a>
            </div>
            <div class='bg-white rounded-lg shadow-lg p-6'>
                <h3 class='text-xl font-semibold mb-2'>⚡ API Ready</h3>
                <p class='text-gray-600 mb-4'>RESTful APIs</p>
                <a href='/api/health' class='bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700'>Test API</a>
            </div>
            <div class='bg-white rounded-lg shadow-lg p-6'>
                <h3 class='text-xl font-semibold mb-2'>📚 Framework</h3>
                <p class='text-gray-600 mb-4'>Learn more</p>
                <a href='/about' class='bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700'>About</a>
            </div>
        </div>
    </main>
    
    <footer class='bg-gray-800 text-white py-8 mt-12'>
        <div class='max-w-7xl mx-auto px-4 text-center'>
            <p>&copy; " . date('Y') . " Brick Framework | Memory-Safe Edition</p>
        </div>
    </footer>
</body>
</html>";
  }

  private function generateDashboard(array $data): string
  {
    $title = htmlspecialchars($data['title'] ?? 'Dashboard');
    $stats = $data['stats'] ?? [];

    $memory = htmlspecialchars($stats['memory']['current_formatted'] ?? 'N/A');
    $php = htmlspecialchars($stats['php']['version'] ?? PHP_VERSION);
    $time = htmlspecialchars($stats['server']['time'] ?? date('H:i:s'));

    return "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-50 min-h-screen'>
    <nav class='bg-white shadow-lg'>
        <div class='max-w-7xl mx-auto px-4 py-4'>
            <h1 class='text-xl font-bold'><a href='/'>Brick Framework</a></h1>
        </div>
    </nav>
    
    <main class='max-w-7xl mx-auto py-6 px-4'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold mb-6'>{$title}</h1>
            
            <div class='grid md:grid-cols-3 gap-6'>
                <div class='bg-blue-50 p-6 rounded-lg'>
                    <h3 class='font-semibold mb-2'>Memory Usage</h3>
                    <p class='text-2xl text-blue-600'>{$memory}</p>
                </div>
                <div class='bg-green-50 p-6 rounded-lg'>
                    <h3 class='font-semibold mb-2'>PHP Version</h3>
                    <p class='text-2xl text-green-600'>{$php}</p>
                </div>
                <div class='bg-purple-50 p-6 rounded-lg'>
                    <h3 class='font-semibold mb-2'>Server Time</h3>
                    <p class='text-2xl text-purple-600'>{$time}</p>
                </div>
            </div>
            
            <div class='mt-6'>
                <a href='/' class='bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700'>← Back to Home</a>
            </div>
        </div>
    </main>
</body>
</html>";
  }

  private function generateAboutPage(array $data): string
  {
    $title = htmlspecialchars($data['title'] ?? 'About Brick Framework');

    return "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-50 min-h-screen'>
    <nav class='bg-white shadow-lg'>
        <div class='max-w-7xl mx-auto px-4 py-4'>
            <h1 class='text-xl font-bold'><a href='/'>Brick Framework</a></h1>
        </div>
    </nav>
    
    <main class='max-w-7xl mx-auto py-6 px-4'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold mb-6'>{$title}</h1>
            <p class='text-gray-600 mb-4'>Brick Framework ist ein modernes PHP-Framework mit MVC-Architektur.</p>
            <p class='text-gray-600 mb-4'>Optimiert für Performance und Memory-Effizienz.</p>
            <p class='text-gray-600'>Built with ❤️ by JP Behrens.</p>
            
            <div class='mt-6'>
                <a href='/' class='bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700'>← Back to Home</a>
            </div>
        </div>
    </main>
</body>
</html>";
  }

  private function generateGenericPage(string $view, array $data): string
  {
    $title = htmlspecialchars($data['title'] ?? 'Brick Framework');

    return "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-50'>
    <div class='container mx-auto py-8'>
        <h1 class='text-3xl font-bold mb-4'>{$title}</h1>
        <p>View: {$view}</p>
        <pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>
    </div>
</body>
</html>";
  }

  /**
   * Error-Handling mit Logging
   */
  private function handleError(\Exception $e, string $context = ''): Response
  {
    // In production: Log error, in development: show details
    if ($this->isDebugMode()) {
      $errorData = [
        'error' => true,
        'context' => $context,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ];
    } else {
      $errorData = [
        'error' => true,
        'message' => 'An error occurred. Please try again later.'
      ];
    }

    if ($this->wantsJson()) {
      return $this->apiError($errorData['message'], 500);
    }

    return Response::html("<h1>Error</h1><p>{$errorData['message']}</p>", 500);
  }

  // ===== UTILITY METHODS =====

  private function formatBytes(int $bytes, int $precision = 2): string
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
      $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
  }

  private function getServerLoad(): ?float
  {
    if (function_exists('sys_getloadavg')) {
      $load = sys_getloadavg();
      return $load[0] ?? null;
    }
    return null;
  }

  private function getEnvironment(): string
  {
    return $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
  }

  private function isDebugMode(): bool
  {
    $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false;
    return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
  }

  private function getUptime(): ?float
  {
    return defined('FRAMEWORK_START') ? round(microtime(true) - FRAMEWORK_START, 4) : null;
  }
}
