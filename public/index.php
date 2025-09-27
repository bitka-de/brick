<?php

/**
 * Brick Framework - Hochperformanter Minimaler Front Controller
 * 
 * Ein ultraleichtgewichtiger, aber vollständiger Front Controller mit professionellen Features:
 * - Inline-Router ohne externe Dependencies für maximale Performance
 * - HTTP-Standards konforme Implementierung (RFC 7231)
 * - Automatische URL-Kanonisierung mit 301 Redirects
 * - Vollständige HTTP-Methoden Unterstützung (GET, POST, HEAD, OPTIONS)
 * - Intelligentes Error Handling mit Developer-Experience
 * - CORS-Ready für moderne Web-APIs
 * 
 * @author JP Behrens <https://bitka.de>
 * @version 1.0
 * @since PHP 8.0
 * @license MIT
 * @link https://bitka.de
 */

declare(strict_types=1);

// Projektverzeichnis für spätere Erweiterungen
define('ROOT_PATH', dirname(__DIR__));

// ===== FEHLERBEHANDLUNG (Developer-Friendly) =====
// Globaler Exception Handler für unerwartete Fehler
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "500 Internal Server Error\n\n" . $e;
});

// Error Handler konvertiert PHP-Warnings zu Exceptions für konsistente Behandlung
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ===== REQUEST ANALYSE UND NORMALISIERUNG =====
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// URL-Pfad extrahieren und kanonisieren (verhindert doppelte Inhalte)
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path    = $rawPath === '/' ? '/' : rtrim($rawPath, '/');

// SEO-konforme 301-Weiterleitung auf kanonische URL
if ($rawPath !== $path && !($path === '/' && $rawPath === '/')) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $location = $path . ($queryString ? "?$queryString" : '');
    header('Location: ' . $location, true, 301);
    exit;
}

// ===== ROUTE DEFINITIONEN =====
$routes = [
    'GET' => [
        '/'       => fn() => text("Hello from Brick Framework! 🧱"),
        '/health' => fn() => json([
            'status'    => 'healthy',
            'framework' => 'Brick',
            'timestamp' => time()
        ]),
        '/info'   => fn() => json([
            'framework'      => 'Brick Framework',
            'version'        => '1.0',
            'root_path'      => ROOT_PATH,
            'request_method' => $method,
            'request_path'   => $path,
            'php_version'    => PHP_VERSION,
        ]),
    ],
    'POST' => [
        '/echo'   => fn() => json([
            'message'       => 'Echo from Brick Framework',
            'received_data' => input(),
            'timestamp'     => time()
        ]),
    ],
];

// ===== HTTP-METHODEN BEHANDLUNG =====

// HEAD-Requests wie GET behandeln, aber ohne Response Body (RFC 7231)
if ($method === 'HEAD' && isset($routes['GET'][$path])) {
    ob_start(); // Body unterdrücken für HEAD-Request
    $routes['GET'][$path]();
    ob_end_clean();
    exit;
}

// OPTIONS für API-Discovery und CORS Preflight (RFC 7231 Section 4.3.7)
if ($method === 'OPTIONS') {
    $allowedMethods = getAllowedMethodsForPath($routes, $path);
    header("Allow: $allowedMethods");
    
    // CORS Headers (für moderne Web-APIs - aktivierbar)
    // header('Access-Control-Allow-Origin: *');
    // header('Access-Control-Allow-Methods: ' . $allowedMethods);
    // header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    http_response_code(204); // No Content
    exit;
}

// ===== REQUEST DISPATCHING =====
$handler = $routes[$method][$path] ?? null;
if ($handler) {
    return $handler();
}

// HTTP 405 Method Not Allowed - Pfad existiert, aber falsche Methode
if (pathExistsForDifferentMethod($routes, $path)) {
    header('Allow: ' . getAllowedMethodsForPath($routes, $path));
    http_response_code(405);
    echo '405 Method Not Allowed';
    exit;
}

// HTTP 404 Not Found - Pfad existiert nicht
http_response_code(404);
text('404 Not Found - Route nicht verfügbar in Brick Framework');

// ===== UTILITY FUNCTIONS =====

/**
 * Sendet eine Plain-Text Response mit korrekten HTTP-Headers
 * 
 * @param string $body Der zu sendende Text-Inhalt
 * @param int $code HTTP Status Code (Standard: 200)
 */
function text(string $body, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
}

/**
 * Sendet eine JSON Response mit korrekten Headers und UTF-8 Encoding
 * 
 * @param array $data Die zu serialisierenden Daten
 * @param int $code HTTP Status Code (Standard: 200)
 */
function json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Liest Request-Body universell ein (JSON und Form-Data)
 * Automatische Content-Type Erkennung für flexible API-Unterstützung
 * 
 * @return array Geparste Request-Daten
 */
function input(): array {
    $rawInput = file_get_contents('php://input') ?: '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // JSON Content-Type erkennen und parsen
    if (str_starts_with($contentType, 'application/json')) {
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    // Fallback auf Standard POST-Daten
    return $_POST ?? [];
}

/**
 * Prüft ob ein Pfad für andere HTTP-Methoden definiert ist
 * Benötigt für korrekte 405 Method Not Allowed Responses
 * 
 * @param array $routes Route-Definitionen
 * @param string $path Zu prüfender Pfad
 * @return bool True wenn Pfad für andere Methoden existiert
 */
function pathExistsForDifferentMethod(array $routes, string $path): bool {
    foreach ($routes as $method => $methodRoutes) {
        if (isset($methodRoutes[$path])) return true;
    }
    return false;
}

/**
 * Generiert Allow-Header für HTTP OPTIONS und 405 Responses
 * Inkludiert automatisch HEAD für GET-Routen und OPTIONS für alle Pfade
 * 
 * @param array $routes Route-Definitionen
 * @param string $path Pfad für den die erlaubten Methoden ermittelt werden
 * @return string Komma-separierte Liste erlaubter HTTP-Methoden
 */
function getAllowedMethodsForPath(array $routes, string $path): string {
    $methods = [];
    
    // Alle definierten Methoden für diesen Pfad sammeln
    foreach ($routes as $method => $methodRoutes) {
        if (isset($methodRoutes[$path])) {
            $methods[] = $method;
        }
    }
    
    // HEAD automatisch hinzufügen wenn GET existiert (RFC 7231)
    if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
        $methods[] = 'HEAD';
    }
    
    // OPTIONS ist immer verfügbar
    if (!in_array('OPTIONS', $methods)) {
        $methods[] = 'OPTIONS';
    }
    
    sort($methods);
    return implode(', ', array_unique($methods));
}
