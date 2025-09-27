<?php
/**
 * Routes Configuration
 * 
 * Definiert alle HTTP-Routes für die Anwendung
 */

use Core\Router;
use Core\View;
use App\Controllers\HomeController;

return function (Router $router, $app) {
    
    // ===== API Routes =====
    $router->group('/api', function (Router $r) {
        
        // Health Check
        $r->get('/health', function ($request, $params) {
            $controller = new HomeController($request, \Core\Response::make());
            return $controller->healthCheck();
        });
        
        // Framework Info
        $r->get('/info', function ($request, $params) {
            return \Core\Response::json([
                'framework' => 'Brick Framework',
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'loaded_extensions' => get_loaded_extensions(),
                'memory_usage' => memory_get_usage(true),
                'uptime' => defined('FRAMEWORK_START') ? round(microtime(true) - FRAMEWORK_START, 4) : null
            ]);
        });
        
        // Echo Test (für POST/PUT/PATCH Testing)
        $r->post('/echo', function ($request, $params) {
            return \Core\Response::json([
                'method' => $request->method(),
                'headers' => $request->headers(),
                'query' => $request->query(),
                'body' => $request->input(),
                'files' => $request->files()
            ]);
        });
        
        $r->match(['PUT', 'PATCH'], '/echo', function ($request, $params) {
            return \Core\Response::json([
                'method' => $request->method(),
                'input' => $request->input()
            ]);
        });
        
    }, [/* API Middleware hier */]);
    
    // ===== Web Routes =====
    
    // Homepage
    $router->get('/', function ($request, $params) {
        $controller = new HomeController($request, \Core\Response::make());
        return $controller->index();
    });
    
    // Dashboard (beispielhafte Controller-Route)
    $router->get('/dashboard', function ($request, $params) {
        $controller = new HomeController($request, \Core\Response::make());
        return $controller->dashboard();
    });
    
    // About Page
    $router->get('/about', function ($request, $params) {
        $controller = new HomeController($request, \Core\Response::make());
        return $controller->about();
    });
    
    // User Routes mit Parameter
    $router->get('/users/{id}', function ($request, $params) {
        $userId = (int) $params['id'];
        
        return \Core\Response::json([
            'user_id' => $userId,
            'message' => "User details for ID: {$userId}"
        ]);
    });
    
    $router->get('/users/{id}/posts/{slug?}', function ($request, $params) {
        return \Core\Response::json([
            'user_id' => $params['id'],
            'post_slug' => $params['slug'] ?? 'all-posts',
            'message' => 'User posts endpoint'
        ]);
    });
    
    // Form Example
    $router->get('/contact', function ($request, $params) {
        return \Core\Response::html('
            <!DOCTYPE html>
            <html><head><title>Contact Us</title><script src="https://cdn.tailwindcss.com"></script></head>
            <body class="bg-gray-50 p-8">
                <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-8">
                    <h1 class="text-3xl font-bold mb-6">Contact Us</h1>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="name" placeholder="Name" class="w-full p-3 border rounded">
                        <input type="email" name="email" placeholder="Email" class="w-full p-3 border rounded">
                        <textarea name="message" placeholder="Message" rows="4" class="w-full p-3 border rounded"></textarea>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded hover:bg-blue-700">Send Message</button>
                    </form>
                    <a href="/" class="inline-block mt-4 text-gray-600 hover:text-gray-900">← Back to Home</a>
                </div>
            </body></html>
        ');
    });
    
    $router->post('/contact', function ($request, $params) {
        $data = $request->input();
        
        // Simple validation
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Name is required';
        if (empty($data['email'])) $errors['email'] = 'Email is required';
        if (empty($data['message'])) $errors['message'] = 'Message is required';
        
        if (!empty($errors)) {
            return \Core\Response::json([
                'success' => false,
                'errors' => $errors
            ], 422);
        }
        
        return \Core\Response::json([
            'success' => true,
            'message' => 'Thank you for your message!',
            'data' => $data
        ]);
    });
    
    // File Upload Example
    $router->get('/upload', function ($request, $params) {
        return \Core\Response::html('
            <!DOCTYPE html>
            <html><head><title>File Upload</title><script src="https://cdn.tailwindcss.com"></script></head>
            <body class="bg-gray-50 p-8">
                <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-8">
                    <h1 class="text-3xl font-bold mb-6">File Upload</h1>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="file" name="file" class="w-full p-3 border rounded">
                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700">Upload File</button>
                    </form>
                    <a href="/" class="inline-block mt-4 text-gray-600 hover:text-gray-900">← Back to Home</a>
                </div>
            </body></html>
        ');
    });
    
    $router->post('/upload', function ($request, $params) {
        $files = $request->files();
        
        if (empty($files)) {
            return \Core\Response::json([
                'success' => false,
                'message' => 'No files uploaded'
            ], 400);
        }
        
        return \Core\Response::json([
            'success' => true,
            'message' => 'Files processed',
            'files' => array_keys($files)
        ]);
    });
    
    // Testing & Development Routes (nur in Development)
    if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
        
        $router->get('/test/database', function ($request, $params) {
            try {
                $db = \Core\Database::connection();
                $result = $db->query('SELECT 1 as test')->fetch();
                
                return \Core\Response::json([
                    'database_status' => 'connected',
                    'test_query' => $result,
                    'driver' => $db->getAttribute(PDO::ATTR_DRIVER_NAME)
                ]);
            } catch (\Exception $e) {
                return \Core\Response::json([
                    'database_status' => 'error',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
        
        $router->get('/test/view', function ($request, $params) {
            return \Core\Response::html('
                <!DOCTYPE html>
                <html><head><title>View System Test</title><script src="https://cdn.tailwindcss.com"></script></head>
                <body class="bg-gray-50 p-8">
                    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-8">
                        <h1 class="text-3xl font-bold mb-6">View System Test</h1>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="border p-4 rounded">
                                <h3 class="font-semibold mb-2">Memory-Safe Templates</h3>
                                <p>✅ No complex template compilation</p>
                                <p>✅ Direct HTML generation</p>
                                <p>✅ Memory-optimized rendering</p>
                            </div>
                            <div class="border p-4 rounded">
                                <h3 class="font-semibold mb-2">System Info</h3>
                                <p><strong>PHP:</strong> ' . PHP_VERSION . '</p>
                                <p><strong>Memory:</strong> ' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB</p>
                                <p><strong>Framework:</strong> Brick v1.0</p>
                            </div>
                        </div>
                        <a href="/" class="inline-block mt-6 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">← Back to Home</a>
                    </div>
                </body></html>
            ');
        });
        
        $router->get('/test/cache', function ($request, $params) {
            // Simple Cache-Test mit File-System
            $cacheFile = ROOT_PATH . '/storage/cache/test_cache.txt';
            $cached = false;
            
            if (file_exists($cacheFile) && filemtime($cacheFile) > time() - 60) {
                $data = file_get_contents($cacheFile);
                $cached = true;
            } else {
                $data = 'Generated at: ' . date('Y-m-d H:i:s');
                file_put_contents($cacheFile, $data);
            }
            
            return \Core\Response::json([
                'cache_status' => $cached ? 'hit' : 'miss',
                'data' => $data,
                'timestamp' => time()
            ]);
        });
        
        $router->get('/phpinfo', function ($request, $params) {
            ob_start();
            phpinfo();
            $output = ob_get_clean();
            return \Core\Response::html($output);
        });
    }
};