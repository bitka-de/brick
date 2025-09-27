<?php

declare(strict_types=1);

namespace Core;

/**
 * BadRequestException - Wird bei invaliden HTTP-Requests geworfen.
 */
final class BadRequestException extends \RuntimeException {}

/**
 * Request - Enterprise-Ready HTTP Request Handler für das Brick Framework
 *
 * Diese Klasse bietet eine vollständige, sichere und performante HTTP-Request-Implementierung
 * für moderne PHP-Webanwendungen mit Enterprise-Level Features.
 *
 * ## 🔒 Security Features:
 * - **Trusted Proxies**: CIDR/IP-basierte Whitelist für Load Balancer/CDN
 *   Beispiel: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']
 * - **Host Header Protection**: Wildcard & Regex-basierte Host-Validierung
 *   Beispiel: ['example.com', '*.api.example.com', '/^.*\.secure\.com$/i']
 * - **Strict JSON Validation**: Detaillierte Parser-Errors mit json_last_error_msg()
 * - **URL Security**: Null-Byte Protection & sichere URL-Dekodierung
 *
 * ## 🌐 HTTP Standards Compliance:
 * - **RFC 3986**: URI Syntax & Pfad-Normalisierung (Dot-Segment Removal)
 * - **RFC 7231**: HTTP Semantics & Content-Negotiation (Accept/Accept-Language)
 * - **RFC 7239**: Forwarded Header (moderne Alternative zu X-Forwarded-*)
 * - **Method Override**: PUT/PATCH/DELETE über POST mit Whitelist-Validierung
 *
 * ## ⚡ Developer Experience & Performance:
 * - **Immutable Design**: Thread-safe mit readonly Properties
 * - **Memoization**: Gecachte Accept/Language Parsing für Performance
 * - **Structured File Uploads**: Normalisierte $_FILES Verarbeitung
 * - **Attribute Bag**: PSR-7 inspiriertes Metadata-System für Middleware
 *
 * ## 📡 Proxy & Networking:
 * - **IPv4/IPv6 CIDR Support**: Vollständige IP-Range Validierung
 * - **Multi-Proxy Chain**: Sichere X-Forwarded-For Chain-Analyse
 * - **Authority Detection**: Schema/Host/Port mit Proxy-Awareness
 *
 * ## Usage Examples:
 * ```php
 * // Basis-Setup
 * Request::setTrustedProxies(['10.0.0.0/8', '172.16.0.0/12']);
 * Request::setTrustedHosts(['example.com', '*.api.example.com']);
 * $request = Request::fromGlobals();
 *
 * // Request-Daten abrufen
 * $userInput = $request->inputString('username', 'anonymous');
 * $isApiCall = $request->expectsJson();
 * $clientIpAddress = $request->ip();
 * $preferredLanguage = array_key_first($request->languages());
 *
 * // Middleware-Daten speichern
 * $requestWithAuth = $request->withAttribute('user_id', 42);
 * $userId = $requestWithAuth->attribute('user_id');
 * ```
 *
 * @author JP Behrens <https://bitka.de>
 * @version 2.5
 * @since PHP 8.1
 * @link https://bitka.de/brick-framework
 */
final class Request
{
    // ===== CONSTANTS =====

    /** Header für Method Override */
    private const METHOD_OVERRIDE_HEADER = 'X-Http-Method-Override';

    /** Standard-Trusted-Proxies (Production z. B. private Netze, LB, CDN) */
    private const DEFAULT_TRUSTED_PROXIES = []; // z.B. ['10.0.0.0/8','172.16.0.0/12','192.168.0.0/16']

    /** Welche Proxy-Header dürfen verwendet werden */
    private const DEFAULT_TRUSTED_HEADERS = [
        'Forwarded',          // RFC 7239
        'X-Forwarded-Proto',  // Legacy
        'X-Forwarded-Host',
        'X-Forwarded-Port',
        'X-Forwarded-For',
    ];

    // ===== STATIC CONFIG =====

    /** @var string[] CIDR-Notation oder einzelne IPs/Hosts (IPv4/IPv6) */
    private static array $trustedProxies = self::DEFAULT_TRUSTED_PROXIES;

    /** @var string[] Host-Whitelist (Exakt, '*.domain.tld' oder Regex mit Delimitern) */
    private static array $trustedHosts = [];

    /** @var string[] Erlaubte Proxy-Header (aus obiger Liste wählen) */
    private static array $trustedHeaders = self::DEFAULT_TRUSTED_HEADERS;

    // ===== IMMUTABLE PROPERTIES =====

    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly array $post,
        private readonly ?array $json,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
        private readonly string $scheme,
        private readonly string $host,
        private readonly int $port,
        private readonly string $protocol,
        private readonly string $queryString,
        private readonly array $attributes = []
    ) {}

    // ===== RUNTIME CACHES (memoization) =====
    private ?array $cachedAccept = null;
    private ?array $cachedLanguages = null;

    // ===== STATIC CONFIGURATION METHODS =====

    /**
     * Konfiguriert vertrauenswürdige Proxy-Server für sicheres Header-Processing
     * 
     * ⚠️  WICHTIG: Nur Requests von diesen IP-Ranges werden Proxy-Header (X-Forwarded-*)
     * ausgewertet. Falsche Konfiguration kann zu Security-Issues führen!
     *
     * Production Examples:
     * ```php
     * // Private Networks (Docker, Kubernetes, VMware)
     * Request::setTrustedProxies(['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
     * 
     * // Cloud Load Balancers (AWS ELB, Google Cloud LB, Azure LB)
     * Request::setTrustedProxies(['10.0.0.0/8', '169.254.0.0/16']);
     * 
     * // CDN Networks (Cloudflare, Fastly, AWS CloudFront)
     * Request::setTrustedProxies(['103.21.244.0/22', '103.22.200.0/22', '173.245.48.0/20']);
     * ```
     * 
     * @param string[] $cidrOrIps Array von CIDR-Notationen oder einzelnen IPs
     *                           IPv4: '192.168.1.0/24', '10.0.0.1'
     *                           IPv6: '2001:db8::/32', '::1'
     */
    public static function setTrustedProxies(array $cidrOrIps): void
    {
        self::$trustedProxies = $cidrOrIps;
    }

    /**
     * Konfiguriert Host-Header Whitelist gegen Host Header Injection Attacks
     * 
     * Schützt vor Host Header Poisoning durch Validierung des HTTP Host Headers
     * gegen bekannte erlaubte Werte. Verhindert Cache-Poisoning und DNS-Rebinding.
     * 
     * Pattern Examples:
     * ```php
     * Request::setTrustedHosts([
     *     'example.com',              // Exact match
     *     'api.example.com',          // Exact subdomain
     *     '*.example.com',            // Wildcard für alle Subdomains
     *     '/^.*\.secure\.com$/i',     // Regex mit Delimitern + Flags
     *     '/^(api|admin)\.bitka\.de$/i' // Mehrere spezifische Subdomains
     * ]);
     * ```
     *
     * @param string[] $hostPatterns Array von Host-Validation-Patterns
     *                              Exakt: ['example.com', 'api.example.com'] 
     *                              Wildcard: ['*.example.com'] (alle Subdomains)
     *                              Regex: ['/^.*\.bitka\.de$/i'] (mit Delimitern)
     */
    public static function setTrustedHosts(array $hostPatterns): void
    {
        self::$trustedHosts = $hostPatterns;
    }

    /**
     * Konfiguriert welche Proxy-Header bei Trusted Proxies ausgewertet werden
     * 
     * Granulare Kontrolle über Header-Processing für bessere Security.
     * Empfehlung: Nur benötigte Header aktivieren für minimale Attack-Surface.
     * 
     * Header Priority (RFC Standards):
     * 1. 'Forwarded' (RFC 7239) - Modern standard, most secure
     * 2. 'X-Forwarded-*' (Legacy) - Widely supported but less secure
     * 
     * Security Examples:
     * ```php
     * // Modern setup (RFC 7239 only)
     * Request::setTrustedHeaders(['Forwarded']);
     * 
     * // Legacy compatibility (most common)
     * Request::setTrustedHeaders(['X-Forwarded-For', 'X-Forwarded-Proto', 'X-Forwarded-Host']);
     * 
     * // Full compatibility (default)
     * Request::setTrustedHeaders(['Forwarded', 'X-Forwarded-Proto', 'X-Forwarded-Host', 'X-Forwarded-Port', 'X-Forwarded-For']);
     * ```
     * 
     * @param string[] $headerNames Subset aus: ['Forwarded', 'X-Forwarded-Proto',
     *                              'X-Forwarded-Host', 'X-Forwarded-Port', 'X-Forwarded-For']
     */
    public static function setTrustedHeaders(array $headerNames): void
    {
        self::$trustedHeaders = $headerNames;
    }

    // ===== FACTORY =====

    /**
     * Erstellt eine neue Request-Instanz aus PHP-Superglobals mit vollständiger Validierung
     *
     * Diese Factory-Methode verarbeitet $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
     * und php://input zu einem sauberen, validierten Request-Objekt.
     *
     * Verarbeitungsschritte:
     * 1. URL-Processing: Sichere Dekodierung + RFC 3986 Normalisierung
     * 2. Header-Normalization: Pascal-Case + Authorization Fallbacks
     * 3. Body-Parsing: Strict JSON-Validation mit Error-Details
     * 4. Authority-Detection: Proxy-aware Schema/Host/Port Resolution
     * 5. Security-Validation: Host-Whitelist + Method-Override Protection
     * 6. Data-Structuring: File-Upload Normalization + Server-Var Filtering
     *
     * @return self Neue immutable Request-Instanz mit vollständig validierten Daten
     * @throws BadRequestException Bei Security/Validation-Fehlern:
     *                            - Invalid URL encoding (Null-Bytes, malformed)
     *                            - Malformed JSON payload (mit json_last_error_msg())
     *                            - Untrusted Host header (nicht in Whitelist)
     *                            - Invalid HTTP method (nicht in Whitelist)
     */
    public static function fromGlobals(): self
    {
        $serverVariables = $_SERVER ?? [];
        $requestUriString = $serverVariables['REQUEST_URI'] ?? '/';
        $urlPathComponent = parse_url($requestUriString, PHP_URL_PATH) ?: '/';

        // Sichere URL-Dekodierung + Null-Byte-Protection
        $decodedUrlPath = rawurldecode(str_replace("\0", '', $urlPathComponent));
        if ($decodedUrlPath === false) {
            throw new BadRequestException('Invalid URL encoding detected - potential security threat');
        }

        // RFC 3986-konforme Pfadnormalisierung (Dot-Segment Removal)
        $normalizedRequestPath = self::normalizePath($decodedUrlPath);

        // HTTP-Headers zu Standard-Format normalisieren + Authorization Fallbacks
        $normalizedHeaders = self::normalizeHeaders($serverVariables);

        // Request-Body intelligent parsen mit strenger JSON-Validation
        $requestBodyContent = file_get_contents('php://input') ?: '';
        $contentTypeHeader = strtolower($normalizedHeaders['Content-Type'] ?? '');
        $primaryMediaType = trim(strtok($contentTypeHeader, ';'));
        $parsedJsonPayload = null;
        $formPostData = $_POST ?? [];

        // Strikte JSON-Validation mit detailliertem Error-Reporting
        if ($primaryMediaType === 'application/json' && $requestBodyContent !== '') {
            $decodedJsonData = json_decode($requestBodyContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJsonData)) {
                $parsedJsonPayload = $decodedJsonData;
            } else {
                throw new BadRequestException(
                    'Malformed JSON payload: ' . json_last_error_msg() . ' (Code: ' . json_last_error() . ')'
                );
            }
        }

        // Proxy-aware Authority Detection (Schema/Host/Port)
        [$detectedUrlScheme, $detectedServerHost, $detectedServerPort] = self::detectAuthority($serverVariables, $normalizedHeaders);

        // Host-Whitelist Validation gegen Host Header Injection (Security)
        if (self::$trustedHosts !== [] && !self::hostAllowed($detectedServerHost)) {
            throw new BadRequestException(
                "Untrusted Host header detected: '{$detectedServerHost}' - potential Host Header Injection attack"
            );
        }

        // HTTP-Methode mit Override-Support und Whitelist-Validation
        $requestHttpMethod = strtoupper($serverVariables['REQUEST_METHOD'] ?? 'GET');
        $methodOverrideHeader = $normalizedHeaders[self::headerCase(self::METHOD_OVERRIDE_HEADER)] ?? null;
        if ($methodOverrideHeader && self::isValidHttpMethod($methodOverrideHeader)) {
            $requestHttpMethod = strtoupper($methodOverrideHeader);
        } elseif (isset($_POST['_method']) && self::isValidHttpMethod((string)$_POST['_method'])) {
            $requestHttpMethod = strtoupper((string)$_POST['_method']);
        }

        return new self(
            method:      $requestHttpMethod,
            path:        $normalizedRequestPath,
            query:       $_GET ?? [],
            headers:     $normalizedHeaders,
            post:        $formPostData,
            json:        $parsedJsonPayload,
            cookies:     $_COOKIE ?? [],
            files:       self::normalizeFiles($_FILES ?? []),
            server:      self::extractRelevantServerVars($serverVariables),
            scheme:      $detectedUrlScheme,
            host:        $detectedServerHost,
            port:        $detectedServerPort,
            protocol:    $serverVariables['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            queryString: (string)($serverVariables['QUERY_STRING'] ?? ''),
            attributes:  []
        );
    }

    // ===== CORE DATA ACCESSORS =====

    /** HTTP-Methode abrufen (normalisiert zu Uppercase: GET, POST, PUT, etc.) */
    public function method(): string { return $this->method; }
    
    /** URL-Pfad abrufen (RFC 3986 normalisiert, ohne Query-Parameter: /api/users) */
    public function path(): string { return $this->path; }
    
    /** Query-Parameter abrufen ($_GET Daten als Key-Value Array) */
    public function query(): array { return $this->query; }
    
    /** HTTP-Headers abrufen (normalisiert zu Pascal-Case: Content-Type, Authorization) */
    public function headers(): array { return $this->headers; }
    
    /** POST-Daten abrufen (form-urlencoded/multipart Requests) */
    public function post(): array { return $this->post; }
    
    /** JSON-Payload abrufen (nur bei Content-Type: application/json, sonst null) */
    public function json(): ?array { return $this->json; }
    
    /** HTTP-Cookies abrufen ($_COOKIE Daten) */
    public function cookies(): array { return $this->cookies; }
    
    /** Hochgeladene Dateien abrufen (normalisierte $_FILES Struktur) */
    public function files(): array { return $this->files; }
    
    /** Server-Environment abrufen (gefilterte $_SERVER Variablen) */
    public function server(): array { return $this->server; }
    
    /** URL-Schema abrufen (Proxy-aware: 'http' oder 'https') */
    public function scheme(): string { return $this->scheme; }
    
    /** Server-Host abrufen (Proxy-aware, ohne Port: example.com) */
    public function host(): string { return $this->host; }
    
    /** Server-Port abrufen (Proxy-aware: 80, 443, 8080, etc.) */
    public function port(): int { return $this->port; }
    
    /** HTTP-Protokoll Version abrufen (HTTP/1.1, HTTP/2, etc.) */
    public function protocol(): string { return $this->protocol; }
    
    /** Roher Query-String abrufen (ohne '?' Prefix: param1=value1&param2=value2) */
    public function queryString(): string { return $this->queryString; }

    // ===== CONVENIENCE ACCESS METHODS =====

    /**
     * Holt GET-Parameter mit type-safe Fallback
     * 
     * @param string $key Query-Parameter Name (z.B. 'page', 'limit')
     * @param string|null $default Fallback bei fehlendem Parameter
     * @return string|null Parameter-Wert oder Default
     * 
     * @example $request->get('page', '1') // Gibt '1' zurück wenn ?page= fehlt
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $parameterValue = $this->query[$key] ?? null;
        return is_scalar($parameterValue) ? (string)$parameterValue : $default;
    }

    /**
     * Holt HTTP-Header mit Multi-Value-Support
     * 
     * @param string $name Header-Name (case-insensitive: 'content-type', 'Content-Type')
     * @param string|null $default Fallback bei fehlendem Header
     * @return string|null Header-Wert (bei Multi-Value: 'value1, value2')
     * 
     * @example $request->header('Accept', 'text/html') // Content negotiation
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $normalizedHeaderName = self::headerCase($name);
        $headerValue = $this->headers[$normalizedHeaderName] ?? null;
        if ($headerValue === null) return $default;
        
        return is_array($headerValue) ? implode(', ', $headerValue) : (string)$headerValue;
    }

    /**
     * Holt kombinierte Input-Daten (JSON hat Priorität über POST)
     * 
     * @return array Merged Input-Daten: JSON payload oder POST form data
     * 
     * @example 
     * // Content-Type: application/json -> gibt JSON-Daten zurück
     * // Content-Type: application/x-www-form-urlencoded -> gibt POST-Daten zurück
     */
    public function input(): array
    {
        return $this->json ?? $this->post;
    }

    /**
     * Holt HTTP-Cookie mit type-safe Fallback
     * 
     * @param string $name Cookie-Name (case-sensitive)
     * @param string|null $default Fallback bei fehlendem Cookie
     * @return string|null Cookie-Wert oder Default
     * 
     * @example $request->cookie('session_id', 'anonymous')
     */
    public function cookie(string $name, ?string $default = null): ?string
    {
        $cookieValue = $this->cookies[$name] ?? null;
        return is_scalar($cookieValue) ? (string)$cookieValue : $default;
    }

    // ===== REQUEST TYPE DETECTION =====

    /**
     * Prüft ob Request JSON-Content übermittelt (Content-Type: application/json)
     * @return bool True bei JSON-Content-Type Header
     */
    public function isJson(): bool
    {
        $contentTypeHeader = strtolower((string)$this->header('Content-Type', ''));
        return str_starts_with($contentTypeHeader, 'application/json');
    }

    /**
     * Prüft ob Request via AJAX/XMLHttpRequest gesendet wurde
     * @return bool True bei X-Requested-With: XMLHttpRequest Header
     */
    public function isAjax(): bool
    {
        return strtolower((string)$this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Prüft ob Request über HTTPS/SSL gesichert ist (Proxy-aware)
     * @return bool True bei https:// Schema
     */
    public function isSecure(): bool { return $this->scheme === 'https'; }

    /**
     * Prüft HTTP-Methode (case-insensitive)
     * @param string $method Zu prüfende Methode ('GET', 'POST', 'put', etc.)
     * @return bool True wenn Methode übereinstimmt
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Prüft ob Request ein CORS Preflight (OPTIONS + Access-Control-Request-Method)
     * @return bool True bei CORS Preflight Pattern
     */
    public function isPreflight(): bool
    {
        return $this->isMethod('OPTIONS') && (bool)$this->header('Access-Control-Request-Method');
    }

    /**
     * Prüft ob Client JSON-Response erwartet (Content Negotiation)
     * Kombiniert AJAX-Detection + Accept-Header Analysis
     * @return bool True wenn JSON-Response bevorzugt wird
     */
    public function expectsJson(): bool
    {
        if ($this->isAjax() && $this->isJson()) return true;
        $acceptHeader = strtolower((string)$this->header('Accept', ''));
        return str_contains($acceptHeader, 'application/json') || str_contains($acceptHeader, '+json');
    }

    public function bearerToken(): ?string
    {
        $h = (string)$this->header('Authorization', '');
        return (stripos($h, 'Bearer ') === 0) ? trim(substr($h, 7)) : null;
    }

    public function basicAuth(): ?array
    {
        $h = (string)$this->header('Authorization', '');
        if (stripos($h, 'Basic ') !== 0) return null;
        $dec = base64_decode(substr($h, 6), true);
        if ($dec === false || !str_contains($dec, ':')) return null;
        [$u, $p] = explode(':', $dec, 2);
        return ['user' => $u, 'pass' => $p];
    }

    public function fullUrl(bool $withQuery = true): string
    {
        $authority = $this->host;
        $defaultPort = $this->isSecure() ? 443 : 80;
        if ($this->port !== $defaultPort) $authority .= ':' . $this->port;
        $qs = ($withQuery && $this->queryString !== '') ? '?' . $this->queryString : '';
        return "{$this->scheme}://{$authority}{$this->path}{$qs}";
    }

    public function prefers(array $types, string $default = 'text/html'): string
    {
        $this->cachedAccept ??= self::parseAccept((string)$this->header('Accept', '*/*'));
        foreach ($this->cachedAccept as $mime => $_q) {
            foreach ($types as $t) {
                if (self::acceptsMime($mime, $t)) return $t;
            }
        }
        return $default;
    }

    /** @return array<string,float> z. B. ['de-DE'=>1.0,'de'=>0.9,'en'=>0.8] */
    public function languages(): array
    {
        return $this->cachedLanguages
            ??= self::parseAccept((string)$this->header('Accept-Language', ''), normalizer: static fn(string $v) => str_replace('_', '-', $v));
    }

    // ===== ATTRIBUTE-BAG (immutables) =====

    public function attributes(): array { return $this->attributes; }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self(
            method:      $this->method,
            path:        $this->path,
            query:       $this->query,
            headers:     $this->headers,
            post:        $this->post,
            json:        $this->json,
            cookies:     $this->cookies,
            files:       $this->files,
            server:      $this->server,
            scheme:      $this->scheme,
            host:        $this->host,
            port:        $this->port,
            protocol:    $this->protocol,
            queryString: $this->queryString,
            attributes:  $this->attributes + [$key => $value]
        );
    }

    // ===== NETWORK / PROXY =====

    /**
     * Ermittelt die echte Client-IP-Adresse mit Proxy-Awareness und Trust-Policy
     *
     * Diese Methode implementiert eine sichere IP-Detection-Strategie:
     * 1. Prüft ob REMOTE_ADDR in der Trusted-Proxy-Liste steht
     * 2. Falls nicht vertrauenswürdig: Gibt REMOTE_ADDR zurück (direkter Client)
     * 3. Falls vertrauenswürdig: Analysiert Proxy-Header in folgender Reihenfolge:
     *    - RFC 7239 'Forwarded: for=...' Header (modern, bevorzugt)
     *    - Legacy 'X-Forwarded-For' Header (letzter gültiger öffentlicher IP)
     * 4. Filtert private/reserved IP-Ranges für bessere Sicherheit
     *
     * Security: Verhindert IP-Spoofing durch Whitelist-basierte Proxy-Validation
     *
     * @return string Die ermittelte Client-IP (IPv4 oder IPv6)
     */
    public function ip(): string
    {
        $directConnectionIpAddress = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        // Kein trusted proxy? -> REMOTE_ADDR ist Client-IP (direkter Request)
        if (!self::ipInCidrs($directConnectionIpAddress, self::$trustedProxies)) {
            return $directConnectionIpAddress;
        }

        // RFC 7239 Forwarded: for=... (moderne Proxy-Standard)
        if (in_array('Forwarded', self::$trustedHeaders, true)) {
            $forwardedHeaderValue = (string)$this->header('Forwarded', '');
            if ($forwardedHeaderValue !== '') {
                foreach (explode(',', $forwardedHeaderValue) as $forwardedPairEntry) {
                    foreach (explode(';', trim($forwardedPairEntry)) as $keyValuePair) {
                        [$parameterKey, $parameterValue] = array_map('trim', explode('=', $keyValuePair, 2));
                        if (strcasecmp($parameterKey, 'for') === 0) {
                            $extractedClientIp = trim($parameterValue, "\"'[]"); // IPv6 [::1] → ::1
                            if (filter_var($extractedClientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                                return $extractedClientIp;
                            }
                        }
                    }
                }
            }
        }

        // Legacy X-Forwarded-For (älterer Proxy-Standard)
        if (in_array('X-Forwarded-For', self::$trustedHeaders, true)) {
            $xForwardedForHeaderValue = (string)$this->header('X-Forwarded-For', '');
            if ($xForwardedForHeaderValue !== '') {
                $ipAddressChain = array_map('trim', explode(',', $xForwardedForHeaderValue));
                // Reverse-Iteration: Letzter vertrauenswürdiger Eintrag ist meist die echte Client-IP
                foreach (array_reverse($ipAddressChain) as $candidateIpAddress) {
                    if (filter_var($candidateIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $candidateIpAddress;
                    }
                }
            }
        }

        // Fallback: Direkte Verbindungs-IP zurückgeben
        return $directConnectionIpAddress;
    }

    // ===== PRIVATE UTILITIES =====

    private static function isValidHttpMethod(string $method): bool
    {
        // Konservativ (TRACE/CONNECT standardmäßig weggelassen)
        static $valid = ['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS','PURGE','LOCK','UNLOCK','PROPFIND','PROPPATCH'];
        return in_array(strtoupper($method), $valid, true);
    }

    private static function normalizeHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = self::headerCase(substr($key, 5));
                $headers[$name] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length', 'CONTENT_MD5' => 'Content-Md5'] as $k => $h) {
            if (isset($server[$k])) $headers[$h] = $server[$k];
        }

        // Authorization – mehrere mögliche Quellen
        if (isset($server['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $server['HTTP_AUTHORIZATION'];
        } elseif (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (!isset($headers['Authorization']) && function_exists('apache_request_headers')) {
            $apache = @apache_request_headers();
            if (is_array($apache) && isset($apache['Authorization'])) {
                $headers['Authorization'] = $apache['Authorization'];
            }
        }

        return $headers;
    }

    private static function headerCase(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace(['_', '-'], ' ', $name))));
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;
        $path = preg_replace('#/+#', '/', $path) ?? '/';

        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($segments); continue; }
            $segments[] = $seg;
        }
        $normalized = '/' . implode('/', $segments);
        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    private static function extractRelevantServerVars(array $server): array
    {
        $keys = [
            'REQUEST_TIME','REQUEST_TIME_FLOAT','SERVER_PROTOCOL','REQUEST_SCHEME',
            'SERVER_NAME','SERVER_PORT','HTTPS','REMOTE_ADDR','REMOTE_PORT','SCRIPT_NAME',
            'REQUEST_URI','QUERY_STRING','DOCUMENT_ROOT','HTTP_HOST','HTTP_USER_AGENT','HTTP_REFERER'
        ];
        $out = [];
        foreach ($keys as $k) if (array_key_exists($k, $server)) $out[$k] = $server[$k];
        return $out;
    }

    /**
     * @return array{0:string,1:string,2:int} [scheme, host, port]
     */
    private static function detectAuthority(array $server, array $headers): array
    {
        $isHttps = (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off');
        $scheme  = $isHttps ? 'https' : 'http';

        // HTTP_HOST bevorzugen (kann Port enthalten)
        $hostHeader = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';
        [$host, $port] = self::splitHostPort($hostHeader, $isHttps ? 443 : 80);

        $remoteAddr = $server['REMOTE_ADDR'] ?? '';
        $trusted    = self::ipInCidrs($remoteAddr, self::$trustedProxies);

        // RFC 7239 Forwarded priorisieren
        if ($trusted && in_array('Forwarded', self::$trustedHeaders, true) && isset($headers['Forwarded'])) {
            foreach (explode(',', (string)$headers['Forwarded']) as $pair) {
                foreach (explode(';', trim($pair)) as $kv) {
                    [$k, $v] = array_map('trim', explode('=', $kv, 2));
                    $v = trim($v, "\"'");
                    if (strcasecmp($k, 'proto') === 0) $scheme = strtolower($v);
                    if (strcasecmp($k, 'host')  === 0) [$host, $port] = self::splitHostPort($v, $scheme === 'https' ? 443 : 80);
                }
            }
        } elseif ($trusted) {
            // Legacy X-Forwarded-*
            if (in_array('X-Forwarded-Proto', self::$trustedHeaders, true) && isset($headers['X-Forwarded-Proto'])) {
                $proto = strtolower(trim((string)$headers['X-Forwarded-Proto']));
                if ($proto === 'http' || $proto === 'https') $scheme = $proto;
            }
            if (in_array('X-Forwarded-Host', self::$trustedHeaders, true) && isset($headers['X-Forwarded-Host'])) {
                $xfh = trim(explode(',', (string)$headers['X-Forwarded-Host'])[0]);
                [$host, $port] = self::splitHostPort($xfh, $scheme === 'https' ? 443 : 80);
            }
            if (in_array('X-Forwarded-Port', self::$trustedHeaders, true) && isset($headers['X-Forwarded-Port']) && is_numeric($headers['X-Forwarded-Port'])) {
                $port = (int)$headers['X-Forwarded-Port'];
            }
        }

        return [$scheme, $host, $port];
    }

    /**
     * Teilt Hostheader in Host (IPv4/Name/IPv6) + Port.
     *
     * @return array{0:string,1:int}
     */
    private static function splitHostPort(string $hostHeader, int $defaultPort): array
    {
        // IPv6 in Klammern: [2001:db8::1]:8443
        if (preg_match('/^\[(.+)\](?::(\d+))?$/', $hostHeader, $m)) {
            return [$m[1], isset($m[2]) ? (int)$m[2] : $defaultPort];
        }
        if (str_contains($hostHeader, ':')) {
            [$h, $p] = explode(':', $hostHeader, 2);
            return [$h, is_numeric($p) ? (int)$p : $defaultPort];
        }
        return [$hostHeader, $defaultPort];
    }

    private static function ipInCidrs(string $ip, array $cidrs): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        foreach ($cidrs as $cidr) if (self::ipInCidr($ip, $cidr)) return true;
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) return $ip === $cidr;

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int)$bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong     = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) return false;
            $mask = -1 << (32 - $bits);
            $subnetLong &= $mask;
            return ($ipLong & $mask) === $subnetLong;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $snBin = inet_pton($subnet);
            if ($ipBin === false || $snBin === false) return false;

            $bytes = intdiv($bits, 8);
            $rest  = $bits % 8;

            for ($i = 0; $i < $bytes; $i++) {
                if ($ipBin[$i] !== $snBin[$i]) return false;
            }
            if ($rest > 0) {
                $mask = chr(0xFF << (8 - $rest) & 0xFF);
                return ($ipBin[$bytes] & $mask) === ($snBin[$bytes] & $mask);
            }
            return true;
        }

        return false;
    }

    private static function hostAllowed(string $host): bool
    {
        foreach (self::$trustedHosts as $pattern) {
            // Exakt
            if ($host === $pattern) return true;

            // Wildcard: *.example.com
            if (str_starts_with($pattern, '*.')) {
                $domain = substr($pattern, 2);
                if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
            }

            // Regex: Delimiter + optionale Flags, z. B. '/^.*\.bitka\.de$/i'
            if (preg_match('/^\/.*\/[a-z]*$/i', $pattern) === 1) {
                if (@preg_match($pattern, $host) === 1) return true;
            }
        }
        return false;
    }

    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        $rearrange = static function (array $file, string $keyPrefix = '') use (&$rearrange, &$normalized): void {
            if (isset($file['name']) && is_array($file['name'])) {
                foreach ($file['name'] as $idx => $_) {
                    $rearrange([
                        'name'     => $file['name'][$idx] ?? '',
                        'type'     => $file['type'][$idx] ?? '',
                        'tmp_name' => $file['tmp_name'][$idx] ?? '',
                        'error'    => $file['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $file['size'][$idx] ?? 0,
                    ], $keyPrefix === '' ? (string)$idx : $keyPrefix . '.' . $idx);
                }
            } else {
                $normalized[$keyPrefix] = [
                    'name'     => $file['name']     ?? '',
                    'type'     => $file['type']     ?? '',
                    'tmp_name' => $file['tmp_name'] ?? '',
                    'error'    => $file['error']    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $file['size']     ?? 0,
                ];
            }
        };

        foreach ($files as $key => $spec) {
            if (is_array($spec) && isset($spec['name'])) {
                $rearrange($spec, (string)$key);
            }
        }

        return $normalized;
    }

    /**
     * Accept*-Header Parser (liefert nach q gewichtet, absteigend sortiert)
     *
     * @param callable(string):string|null $normalizer
     * @return array<string,float>
     */
    private static function parseAccept(string $header, ?callable $normalizer = null): array
    {
        if ($header === '') return ['*/*' => 1.0];

        $parts  = array_map('trim', explode(',', $header));
        $result = [];

        foreach ($parts as $p) {
            $q = 1.0;
            $semi  = explode(';', $p);
            $value = trim(array_shift($semi));

            foreach ($semi as $param) {
                [$k, $v] = array_map('trim', array_pad(explode('=', $param, 2), 2, ''));
                if (strtolower($k) === 'q' && is_numeric($v)) {
                    $q = max(0.0, min(1.0, (float)$v));
                }
            }

            if ($normalizer) $value = $normalizer($value);
            $result[$value] = $q;
        }

        arsort($result, SORT_NUMERIC);
        return $result;
    }

    private static function acceptsMime(string $accept, string $target): bool
    {
        if ($accept === '*/*') return true;
        [$aType, $aSub] = array_pad(explode('/', $accept, 2), 2, '*');
        [$tType, $tSub] = array_pad(explode('/', $target, 2), 2, '*');
        if ($aType !== '*' && $aType !== $tType) return false;
        if ($aSub  !== '*' && $aSub  !== $tSub)  return false;
        return true;
    }

    // ===== TYPED INPUT HELPERS (Type-Safe Request-Data Access) =====

    /**
     * Holt String-Input mit Type-Safety und Default-Fallback
     * 
     * @param string $key Input-Parameter Name (aus JSON oder POST)
     * @param string|null $default Fallback-Wert bei fehlendem/invalid Input
     * @return string|null Validierter String-Wert oder Default
     */
    public function inputString(string $key, ?string $default = null): ?string
    {
        $inputValue = $this->input()[$key] ?? null;
        return is_scalar($inputValue) ? (string)$inputValue : $default;
    }

    /**
     * Holt Integer-Input mit PHP-Filter-Validation
     * 
     * @param string $key Input-Parameter Name (aus JSON oder POST)
     * @param int|null $default Fallback-Wert bei fehlendem/invalid Input
     * @return int|null Validierter Integer-Wert oder Default
     */
    public function inputInt(string $key, ?int $default = null): ?int
    {
        $inputValue = $this->input()[$key] ?? null;
        return filter_var($inputValue, FILTER_VALIDATE_INT) !== false ? (int)$inputValue : $default;
    }

    /**
     * Holt Boolean-Input mit flexibler Validation (true/false/1/0/yes/no)
     * 
     * @param string $key Input-Parameter Name (aus JSON oder POST)
     * @param bool|null $default Fallback-Wert bei fehlendem/invalid Input  
     * @return bool|null Validierter Boolean-Wert oder Default
     */
    public function inputBool(string $key, ?bool $default = null): ?bool
    {
        $inputValue = $this->input()[$key] ?? null;
        $filteredValue = filter_var($inputValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $filteredValue !== null ? $filteredValue : $default;
    }
}
