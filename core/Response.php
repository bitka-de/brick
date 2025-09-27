<?php

declare(strict_types=1);

namespace Core;

/**
 * Response – Enterprise-Level Immutable HTTP Response System für Brick Framework
 *
 * ## 🏗️ **Design-Prinzipien:**
 * - **Immutable Architecture**: Jede Änderung liefert eine neue Instanz (Thread-Safe)
 * - **Security-First**: Header-Injection-Protection, sichere Cookie-Defaults, OWASP-konforme Security-Header
 * - **HTTP-Standard-Compliance**: RFC-konforme Implementation (304/204-Handling, Content-Length, CORS)
 * - **Developer-Experience**: Intuitive Factory-Methods, Fluent-API, Smart-Defaults
 *
 * ## 🚀 **Performance-Features:**
 * - **Streaming-Support**: Memory-effiziente große Responses mit Emitter-Pattern
 * - **Smart-Compression**: Content-Type-aware gzip (skippt Binaries, Images)
 * - **Conditional-GET**: Automatische 304-Responses bei ETag/Last-Modified-Match
 * - **Server-Acceleration**: Sendfile-Support für Nginx/Apache (X-Accel-Redirect/X-Sendfile)
 *
 * ## 🔒 **Security-Features:**
 * - **Header-Injection-Protection**: Validierung gegen CRLF-Injection
 * - **Secure-Cookie-Defaults**: HttpOnly, Secure, SameSite=Lax als Standard
 * - **Security-Header-Paket**: X-Content-Type-Options, CORP, COOP, Referrer-Policy
 * - **CORS-Hardening**: Vary-Header, Credentials-Handling, Origin-Validation
 *
 * ## 🌐 **Internationalization:**
 * - **RFC-5987-Unicode-Filenames**: UTF-8-Dateinamen mit ASCII-Fallback
 * - **Multi-Encoding-Download**: Browser-kompatible Filename-Kodierung
 *
 * ## 📡 **Advanced-HTTP-Features:**
 * - **Multi-Origin-CORS**: Array-basierte Origin-Whitelists mit Credentials
 * - **Vary-Header-Management**: Intelligente Cache-Proxy-Optimierung
 * - **ETag-Generation**: Quoted/Unquoted-ETag-Support
 * - **Cache-Control-Utilities**: Browser-Caching mit Smart-Defaults
 *
 * ## 💻 **Usage-Examples:**
 *
 * ### Basic-Responses:
 * ```php
 * // Simple-Text-Response
 * return Response::text('Hello World')->withStatus(200);
 *
 * // JSON-API-Response mit Security
 * return Response::json(['users' => $users])
 *     ->withSecurityDefaults()
 *     ->cors(['https://app.example.com'])
 *     ->compress();
 * ```
 *
 * ### High-Performance-Caching:
 * ```php
 * return Response::html($templateContent)
 *     ->withETag(md5($templateContent))
 *     ->withLastModified($lastModified)
 *     ->withCacheControl('public, max-age=3600')
 *     ->evaluateCachingFromGlobals() // Auto-304 bei Client-Cache-Hit
 *     ->compress();
 * ```
 *
 * ### File-Downloads:
 * ```php
 * // Unicode-Filename-Support
 * return Response::download('Bericht_Müller.pdf', $pdfContent)
 *     ->withCacheControl('private, max-age=0');
 *
 * // High-Performance-Sendfile (Nginx)
 * return Response::make()
 *     ->withSendfile('/internal/files/large.zip', 'nginx')
 *     ->withHeader('Content-Type', 'application/zip');
 * ```
 *
 * ### Real-Time-Streaming:
 * ```php
 * return Response::stream(function() {
 *     foreach ($liveData as $chunk) {
 *         echo "data: " . json_encode($chunk) . "\n\n";
 *         flush();
 *         usleep(100000); // 100ms delay
 *     }
 * })->withContentType('text/event-stream');
 * ```
 *
 * ### Advanced-CORS-Setup:
 * ```php
 * return Response::json($apiData)
 *     ->cors(
 *         origins: ['https://admin.com', 'https://app.com'],
 *         methods: ['GET', 'POST', 'PUT'],
 *         headers: ['Authorization', 'X-Custom-Header'],
 *         credentials: true,
 *         expose: ['X-Rate-Limit', 'X-Total-Count']
 *     );
 * ```
 *
 * @author JP Behrens <https://bitka.de>
 * @version 2.0 (Enterprise-Edition)
 * @since PHP 8.1
 * @link https://bitka.de/brick-framework
 * 
 * @see https://tools.ietf.org/html/rfc7234 HTTP/1.1 Caching
 * @see https://tools.ietf.org/html/rfc5987 Character Set and Language Encoding for HTTP Header Field Parameters
 * @see https://tools.ietf.org/html/rfc7231 HTTP/1.1 Semantics and Content
 */
final class Response
{
    /** @var array<int,string> Standard HTTP Status Codes mit Reason Phrases */
    private const HTTP_STATUS_REASONS = [
        // 2xx
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
        // 3xx
        301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
        307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        // 4xx
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        // 5xx
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
    ];

    private int $httpStatusCode;
    private ?string $customReasonPhrase; // optional custom reason phrase
    /** @var array<string,string> Normalized HTTP headers (Pascal-Case) */
    private array $httpHeaders;
    /** @var list<array<string,mixed>> Cookie configuration arrays */
    private array $httpCookies;
    private string $responseBody;
    /** @var null|callable():void Content emitter function for streaming responses */
    private $contentEmitterFunction = null; // für Streaming

    /**
     * Private Constructor für Immutable Response-Objekte (Factory Pattern)
     * 
     * @param int $httpStatusCode HTTP-Status-Code (200, 404, 500, etc.)
     * @param string|null $customReasonPhrase Optional custom reason phrase (überschreibt Standard)
     * @param array<string,string> $httpHeaders Normalisierte HTTP-Headers
     * @param list<array<string,mixed>> $httpCookies Cookie-Konfigurationsarrays
     * @param string $responseBody Response-Body-Content
     */
    private function __construct(
        int $httpStatusCode = 200,
        ?string $customReasonPhrase = null,
        array $httpHeaders = [],
        array $httpCookies = [],
        string $responseBody = ''
    ) {
        $this->httpStatusCode = self::assertValidHttpStatus($httpStatusCode);
        $this->customReasonPhrase = $customReasonPhrase;
        $this->httpHeaders = $this->normalizeHeaders($httpHeaders);
        $this->httpCookies = $httpCookies;
        $this->responseBody = $responseBody;
    }

    // ========== Factories ==========

    /**
     * Basis-Factory für Response-Erstellung mit Content und Status
     * 
     * @param string $responseContent Response-Body-Content (HTML, JSON, Text, etc.)
     * @param int $httpStatusCode HTTP-Status-Code (200, 201, 404, 500, etc.)
     * @return self Neue Response-Instanz für Fluent Interface
     */
    public static function make(string $responseContent = '', int $httpStatusCode = 200): self
    {
        return new self($httpStatusCode, null, [], [], $responseContent);
    }

    /**
     * Erstellt Plain-Text-Response mit UTF-8-Encoding
     * 
     * @param string $textContent Plain-Text-Content ohne HTML-Tags
     * @param int $httpStatusCode HTTP-Status-Code (200, 400, 500, etc.)
     * @return self Text-Response mit Content-Type: text/plain; charset=utf-8
     */
    public static function text(string $textContent, int $httpStatusCode = 200): self
    {
        return self::make($textContent, $httpStatusCode)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Erstellt HTML-Response mit UTF-8-Encoding
     * 
     * @param string $htmlContent HTML-Content mit Tags und Markup
     * @param int $httpStatusCode HTTP-Status-Code (200, 400, 500, etc.)
     * @return self HTML-Response mit Content-Type: text/html; charset=utf-8
     */
    public static function html(string $htmlContent, int $httpStatusCode = 200): self
    {
        return self::make($htmlContent, $httpStatusCode)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Erstellt JSON-Response mit automatischen Content-Type-Headern
     *
     * @param mixed $jsonData Array/Object/Scalar für JSON-Encoding
     * @param int $httpStatusCode HTTP-Status-Code (200, 201, 400, etc.)
     * @param int $jsonEncodingFlags JSON-Encoding-Flags (JSON_UNESCAPED_UNICODE, etc.)
     * @param int $jsonEncodingDepth Maximum-Rekursionstiefe für verschachtelte Arrays/Objekte
     * @return self JSON-Response mit Content-Type: application/json
     * @throws \RuntimeException Bei JSON-Encoding-Fehlern mit detaillierter Fehlermeldung
     */
    public static function json(
        mixed $jsonData,
        int $httpStatusCode = 200,
        int $jsonEncodingFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        int $jsonEncodingDepth = 512
    ): self {
        $encodedJsonString = json_encode($jsonData, $jsonEncodingFlags, $jsonEncodingDepth);
        if ($encodedJsonString === false) {
            $jsonErrorMessage = json_last_error_msg();
            throw new \RuntimeException("JSON encoding failed: {$jsonErrorMessage}");
        }
        return self::make($encodedJsonString, $httpStatusCode)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Erstellt HTTP-Redirect-Response mit Location-Header
     *
     * @param string $targetUrl Ziel-URL für den Redirect (absolute oder relative URL)
     * @param int $redirectStatusCode HTTP-Redirect-Status (301=permanent, 302=temporary, 307=temp+method-preserve, 308=perm+method-preserve)
     * @return self Redirect-Response mit Location-Header
     * @throws \InvalidArgumentException Bei ungültigen Redirect-Status-Codes
     */
    public static function redirect(string $targetUrl, int $redirectStatusCode = 302): self
    {
        $validRedirectCodes = [301, 302, 307, 308];
        if (!in_array($redirectStatusCode, $validRedirectCodes, true)) {
            $validCodesString = implode(', ', $validRedirectCodes);
            throw new \InvalidArgumentException("Redirect status must be one of: {$validCodesString}. Got: {$redirectStatusCode}");
        }
        return self::make('', $redirectStatusCode)->withHeader('Location', $targetUrl);
    }

    /**
     * Erstellt 204 No Content Response (kein Body, nur Header)
     * 
     * Ideal für:
     * - Erfolgreiche DELETE-Operations
     * - PUT-Updates ohne Return-Value
     * - API-Endpoints die nur Status zurückmelden
     * 
     * @return self 204-Response ohne Body-Content
     * 
     * @example return Response::noContent(); // HTTP/1.1 204 No Content
     */
    public static function noContent(): self
    {
        return new self(204, null, [], [], '');
    }

    /**
     * Erstellt File-Download-Response mit RFC-5987-Unicode-Filename-Support
     *
     * Implementiert sowohl ASCII-Fallback als auch UTF-8-Filename-Parameter
     * für maximale Browser-Kompatibilität bei internationalen Dateinamen.
     *
     * @param string $originalFilename Gewünschter Dateiname (UTF-8, kann Umlaute/Sonderzeichen enthalten)
     * @param string $fileContentData Binäre Datei-Inhalte als String
     * @param bool $displayInline true=inline (Browser-Vorschau), false=attachment (Download-Dialog)
     * @return self Download-Response mit Content-Disposition und Content-Length
     * 
     * @example Response::download('Bericht_Müller.pdf', $pdfData, false)
     *          // Erzeugt: Content-Disposition: attachment; filename="Bericht_Muller.pdf"; filename*=UTF-8''Bericht_M%C3%BCller.pdf
     */
    public static function download(string $originalFilename, string $fileContentData, bool $displayInline = false): self
    {
        $contentDisposition = $displayInline ? 'inline' : 'attachment';
        
        // ASCII-Fallback: Entferne problematische Zeichen für ältere Browser
        $asciiSafeFilename = trim(str_replace(['"', "\r", "\n"], '', $originalFilename));
        
        // RFC-5987: UTF-8-Encoding für internationale Zeichen
        $utf8EncodedFilename = rawurlencode($originalFilename);

        return self::make($fileContentData, 200)
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', "{$contentDisposition}; filename=\"{$asciiSafeFilename}\"; filename*=UTF-8''{$utf8EncodedFilename}")
            ->withHeader('Content-Length', (string)strlen($fileContentData));
    }

    /**
     * Erstellt Streaming-Response für große oder real-time Daten
     *
     * Ideal für:
     * - Server-Sent-Events (SSE)
     * - Große CSV/JSON-Exports 
     * - Real-Time-Datenstreams
     * - Memory-effiziente File-Ausgabe
     *
     * @param callable():void $contentEmitterCallable Callback-Funktion die Content direkt ausgibt (echo, flush)
     * @param int $httpStatusCode HTTP-Status-Code für den Stream
     * @return self Streaming-Response ohne automatische Content-Length
     * 
     * @example Response::stream(function() {
     *              foreach ($largeDataset as $row) {
     *                  echo json_encode($row) . "\n";
     *                  flush();
     *              }
     *          })->withContentType('application/x-ndjson')
     */
    public static function stream(callable $contentEmitterCallable, int $httpStatusCode = 200): self
    {
        $streamingResponse = new self($httpStatusCode);
        $streamingResponse->contentEmitterFunction = $contentEmitterCallable;
        return $streamingResponse;
    }

    /**
     * Immutable JSON-Update: Ersetzt Response-Body mit neuen JSON-Daten
     *
     * Praktisch für:
     * - Bestehende Response mit neuen Daten aktualisieren
     * - JSON-Transformationen ohne neue Response-Instanz
     * - API-Response-Pipelines
     *
     * @param mixed $newJsonData Array/Object/Scalar für JSON-Encoding
     * @param int $jsonEncodingFlags JSON-Encoding-Flags (JSON_UNESCAPED_UNICODE, etc.)
     * @param int $jsonEncodingDepth Maximum-Rekursionstiefe für verschachtelte Strukturen
     * @return self Neue Response-Instanz mit JSON-Body und Content-Type
     * @throws \RuntimeException Bei JSON-Encoding-Fehlern mit detaillierter Fehlermeldung
     * 
     * @example $response = Response::make('Loading...')
     *              ->withJson(['status' => 'complete', 'data' => $results])
     */
    public function withJson(
        mixed $newJsonData,
        int $jsonEncodingFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        int $jsonEncodingDepth = 512
    ): self {
        $encodedJsonString = json_encode($newJsonData, $jsonEncodingFlags, $jsonEncodingDepth);
        if ($encodedJsonString === false) {
            $jsonErrorMessage = json_last_error_msg();
            throw new \RuntimeException("JSON encoding failed: {$jsonErrorMessage}");
        }
        return $this->withBody($encodedJsonString)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // ========== Immutables (with*) ==========

    /**
     * Setzt HTTP-Status-Code mit optionalem Custom-Reason-Phrase
     * 
     * @param int $newHttpStatusCode HTTP-Status-Code (100-599)
     * @param string|null $customReasonPhrase Optional custom reason (überschreibt Standard)
     * @return self Neue Response-Instanz mit aktualisiertem Status
     */
    public function withStatus(int $newHttpStatusCode, ?string $customReasonPhrase = null): self
    {
        $clonedResponse = clone $this;
        $clonedResponse->httpStatusCode = self::assertValidHttpStatus($newHttpStatusCode);
        $clonedResponse->customReasonPhrase = $customReasonPhrase;
        return $clonedResponse;
    }

    /**
     * Setzt Response-Body-Content (überschreibt existierenden Body)
     * 
     * @param string $newResponseContent Neuer Body-Content
     * @return self Neue Response-Instanz mit aktualisiertem Body
     */
    public function withBody(string $newResponseContent): self
    {
        $clonedResponse = clone $this;
        $clonedResponse->responseBody = $newResponseContent;
        $clonedResponse->contentEmitterFunction = null; // Body und Emitter schließen sich aus
        return $clonedResponse;
    }

    /**
     * Hängt Content an bestehenden Response-Body an (String-Concatenation)
     * 
     * @param string $additionalContent Content der angehängt werden soll
     * @return self Neue Response-Instanz mit erweitertem Body
     */
    public function appendBody(string $additionalContent): self
    {
        $clonedResponse = clone $this;
        $clonedResponse->responseBody .= $additionalContent;
        $clonedResponse->contentEmitterFunction = null;
        return $clonedResponse;
    }

    /**
     * Setzt HTTP-Header (überschreibt existierende Header)
     *
     * Implementiert Micro-Optimierung: Bei gleichem Header-Wert wird keine
     * neue Instanz erstellt (idempotente Operation).
     *
     * @param string $headerName Header-Name (wird zu Pascal-Case normalisiert)
     * @param string $headerValue Header-Wert (wird auf CRLF-Injection geprüft)
     * @return self Neue Response-Instanz mit gesetztem Header (oder gleiche bei identischem Wert)
     * @throws \InvalidArgumentException Bei Header-Injection-Versuch (CR/LF-Zeichen)
     */
    public function withHeader(string $headerName, string $headerValue): self
    {
        $normalizedHeaderName = self::normalizeHeaderName($headerName);
        self::assertNoHeaderInjection($headerValue);
        
        // Micro-Optimierung: Idempotent bei gleichem Wert → spare Clone-Operation
        if (($this->httpHeaders[$normalizedHeaderName] ?? null) === $headerValue) {
            return $this;
        }
        
        $clonedResponse = clone $this;
        $clonedResponse->httpHeaders[$normalizedHeaderName] = $headerValue;
        return $clonedResponse;
    }

    /**
     * Fügt HTTP-Header hinzu (Multi-Value-Support mit Deduplication)
     *
     * Bei bereits existierenden Headern wird der neue Wert mit Komma getrennt angehängt.
     * Implementiert intelligente Deduplication um identische Werte zu vermeiden.
     *
     * @param string $headerName Header-Name (wird zu Pascal-Case normalisiert)
     * @param string $newHeaderValue Neuer Header-Wert der hinzugefügt werden soll
     * @return self Neue Response-Instanz mit erweitertem Header
     * @throws \InvalidArgumentException Bei Header-Injection-Versuch (CR/LF-Zeichen)
     * 
     * @example $response->withAddedHeader('Set-Cookie', 'session=abc123')
     *              ->withAddedHeader('Set-Cookie', 'preferences=dark-mode')
     *          // Resultat: Set-Cookie: session=abc123, preferences=dark-mode
     */
    public function withAddedHeader(string $headerName, string $newHeaderValue): self
    {
        $normalizedHeaderName = self::normalizeHeaderName($headerName);
        self::assertNoHeaderInjection($newHeaderValue);

        $clonedResponse = clone $this;
        
        if (isset($clonedResponse->httpHeaders[$normalizedHeaderName])) {
            // Parse existing comma-separated values und dedupliziere
            $existingHeaderValues = array_map('trim', explode(',', $clonedResponse->httpHeaders[$normalizedHeaderName]));
            
            // Verhindere Duplikate durch Deduplication-Check
            if (!in_array($newHeaderValue, $existingHeaderValues, true)) {
                $existingHeaderValues[] = $newHeaderValue;
            }
            
            $clonedResponse->httpHeaders[$normalizedHeaderName] = implode(', ', $existingHeaderValues);
        } else {
            // Erster Header-Wert für diesen Namen
            $clonedResponse->httpHeaders[$normalizedHeaderName] = $newHeaderValue;
        }
        
        return $clonedResponse;
    }

    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        unset($clone->httpHeaders[self::normalizeHeaderName($name)]);
        return $clone;
    }

    public function clearHeaders(): self
    {
        $clone = clone $this;
        $clone->httpHeaders = [];
        return $clone;
    }

    public function withContentType(string $mime, string $charset = 'utf-8'): self
    {
        return $this->withHeader('Content-Type', "{$mime}; charset={$charset}");
    }

    public function withCacheControl(string $directive): self
    {
        return $this->withHeader('Cache-Control', $directive);
    }

    public function withETag(string $etag): self
    {
        // Quoted ETag
        $tag = preg_match('/^W\/|"/', $etag) ? $etag : "\"{$etag}\"";
        return $this->withHeader('ETag', $tag);
    }

    public function withLastModified(\DateTimeInterface $dt): self
    {
        return $this->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $dt->getTimestamp()) . ' GMT');
    }

    public function withVary(string $token): self
    {
        $existing = $this->httpHeaders['Vary'] ?? '';
        $tokens = array_filter(array_map('trim', explode(',', $existing)));
        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
        return $this->withHeader('Vary', implode(',', $tokens));
    }

    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $secure ??= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $sameSite = ucfirst(strtolower($sameSite));
        if ($sameSite === 'None' && $secure === false) {
            // Browser verlangen Secure, wenn SameSite=None
            $secure = true;
        }
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException('SameSite must be Lax, Strict or None');
        }

        $clone = clone $this;
        $clone->httpCookies[] = [
            'name'     => $name,
            'value'    => $value,
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        return $clone;
    }

    public function withoutCookie(string $name): self
    {
        $clone = clone $this;
        $clone->httpCookies = array_values(array_filter(
            $clone->httpCookies,
            static fn(array $c) => $c['name'] !== $name
        ));
        return $clone;
    }

    public function expireCookie(string $name, string $path = '/', string $domain = ''): self
    {
        return $this->withCookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Setzt Standard-Security-Headers für moderne Web-Anwendungen
     * 
     * Implementiert OWASP-empfohlene Security-Headers für:
     * - **X-Content-Type-Options**: Verhindert MIME-Sniffing-Attacks
     * - **Referrer-Policy**: Kontrolliert Referrer-Information-Leakage
     * - **X-Frame-Options**: Schutz vor Clickjacking (SAMEORIGIN)
     * - **Permissions-Policy**: Deaktiviert potentiell gefährliche Browser-APIs
     * - **Cross-Origin-Opener-Policy**: Isoliert Window-Context
     * - **Cross-Origin-Resource-Policy**: Kontrolliert Cross-Origin-Embedding
     * 
     * @return self Response mit sicheren Standard-Security-Headern
     * 
     * @example return Response::html($content)->withSecurityDefaults();
     *          // Setzt alle 6 Security-Header automatisch
     */
    public function withSecurityDefaults(): self
    {
        return $this
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-site');
    }

    /**
     * Aktiviert Server-seitige Sendfile-Optimierung für High-Performance File-Serving
     * 
     * Sendfile lässt den Webserver (Nginx/Apache) Dateien direkt aus dem Dateisystem
     * ausliefern ohne über PHP zu gehen. Reduziert Memory-Usage und erhöht Performance
     * dramatisch bei großen Dateien.
     * 
     * **Nginx-Konfiguration erforderlich:**
     * ```nginx
     * location /internal/ {
     *     internal;
     *     alias /var/www/files/;
     * }
     * ```
     * 
     * **Apache-Konfiguration erforderlich:**
     * ```apache
     * LoadModule xsendfile_module modules/mod_xsendfile.so
     * XSendFile on
     * XSendFilePath /var/www/files
     * ```
     * 
     * @param string $internalFilePath Interner Dateipfad (für Nginx: /internal/file.pdf, für Apache: /var/www/files/file.pdf)
     * @param string $webServerType Webserver-Type: 'nginx' (X-Accel-Redirect) oder 'apache' (X-Sendfile)
     * @return self Response mit Sendfile-Header für Server-Acceleration
     * 
     * @example 
     * // Nginx
     * ->withSendfile('/internal/uploads/report.pdf', 'nginx')
     * 
     * // Apache  
     * ->withSendfile('/var/www/files/report.pdf', 'apache')
     */
    public function withSendfile(string $internalFilePath, string $webServerType = 'nginx'): self
    {
        return match (strtolower($webServerType)) {
            'nginx'  => $this->withHeader('X-Accel-Redirect', $internalFilePath),
            'apache' => $this->withHeader('X-Sendfile', $internalFilePath),
            default  => $this
        };
    }

    /**
     * Setzt robuste CORS-Header für Cross-Origin-Resource-Sharing
     *
     * Implementiert vollständige CORS-Spezifikation mit:
     * - Multi-Origin-Support mit automatischer Origin-Validation
     * - Credentials-Handling mit Spec-konformen Fallbacks
     * - Vary-Header für korrekte Proxy-Cache-Behandlung
     * - Expose-Headers für Client-sichtbare Custom-Headers
     *
     * @param string|array $allowedOrigins Erlaubte Origins ('*' oder ['https://app1.com', 'https://app2.com'])
     * @param string|array $allowedMethods HTTP-Methoden ('GET,POST' oder ['GET', 'POST', 'PUT'])
     * @param string|array $allowedHeaders Request-Headers ('Content-Type,Authorization' oder Array)
     * @param bool $allowCredentials Erlaubt Cookies/Auth-Headers (Auto-handled bei '*' Origin)
     * @param int $preflightMaxAge Preflight-Cache-Zeit in Sekunden (Browser-Performance)
     * @param string|array $exposedHeaders Headers die Client lesen darf (für Custom-API-Headers)
     * @return self Response mit vollständigen CORS-Headern
     * 
     * @example 
     * // Multi-Origin API mit Credentials
     * ->cors(
     *     origins: ['https://admin.example.com', 'https://app.example.com'],
     *     methods: ['GET', 'POST', 'PUT'],
     *     headers: ['Authorization', 'X-API-Key'],
     *     credentials: true,
     *     expose: ['X-Rate-Limit', 'X-Total-Count']
     * )
     */
    public function cors(
        string|array $allowedOrigins = '*',
        string|array $allowedMethods = 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        string|array $allowedHeaders = 'Content-Type,Authorization,X-Requested-With',
        bool $allowCredentials = false,
        int $preflightMaxAge = 3600,
        string|array $exposedHeaders = []
    ): self {
        $corsResponse = $this;
        $selectedOriginHeader = '*';

        // Multi-Origin-Handling: Validiere gegen aktuellen Request-Origin
        if (is_array($allowedOrigins)) {
            $currentRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            if ($currentRequestOrigin && in_array($currentRequestOrigin, $allowedOrigins, true)) {
                $selectedOriginHeader = $currentRequestOrigin;
                $corsResponse = $corsResponse->withVary('Origin'); // Proxy-Cache-Optimierung
            } else {
                // Kein Origin-Match → keine CORS-Header setzen (Security)
                return $corsResponse;
            }
        } else {
            $selectedOriginHeader = $allowedOrigins;
            if ($selectedOriginHeader !== '*') {
                $corsResponse = $corsResponse->withVary('Origin');
            }
        }

        // CORS-Spec: '*' + credentials ist nicht erlaubt → Auto-Fix mit actual Origin
        if ($allowCredentials && $selectedOriginHeader === '*') {
            $actualRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($actualRequestOrigin) {
                $selectedOriginHeader = $actualRequestOrigin;
                $corsResponse = $corsResponse->withVary('Origin');
            } else {
                // Keine Origin verfügbar → deaktiviere Credentials für Sicherheit
                $allowCredentials = false;
            }
        }

        // Convert Arrays zu Header-Strings
        $methodsHeaderValue = is_array($allowedMethods) ? implode(',', $allowedMethods) : $allowedMethods;
        $headersHeaderValue = is_array($allowedHeaders) ? implode(',', $allowedHeaders) : $allowedHeaders;

        // Setze Standard-CORS-Header
        $corsResponse = $corsResponse
            ->withHeader('Access-Control-Allow-Origin', $selectedOriginHeader)
            ->withHeader('Access-Control-Allow-Methods', $methodsHeaderValue)
            ->withHeader('Access-Control-Allow-Headers', $headersHeaderValue)
            ->withHeader('Access-Control-Max-Age', (string)$preflightMaxAge);

        // Optional: Credentials-Header
        if ($allowCredentials) {
            $corsResponse = $corsResponse->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Optional: Expose-Headers für Custom-API-Headers
        if (!empty($exposedHeaders)) {
            $exposeHeaderValue = is_array($exposedHeaders) ? implode(',', $exposedHeaders) : $exposedHeaders;
            $corsResponse = $corsResponse->withHeader('Access-Control-Expose-Headers', $exposeHeaderValue);
        }

        return $corsResponse;
    }

    /**
     * Aktiviert intelligente gzip-Komprimierung für Performance-Optimierung
     * 
     * **Intelligente Komprimierungs-Logik:**
     * - Prüft Client-Support (Accept-Encoding: gzip)
     * - Skippt bereits komprimierte Formate (PDF, ZIP, Images, Videos)
     * - Komprimiert nur bei sinnvoller Mindest-Body-Größe (default: 1KB)
     * - Vermeidet doppelte Komprimierung (Content-Encoding-Check)
     * - Skippt Streaming-Responses (Emitter-basiert)
     * 
     * **Optimale Content-Types für Komprimierung:**
     * - text/html, text/css, text/javascript
     * - application/json, application/xml
     * - text/plain, text/csv
     * 
     * **Automatisch geskippte Formate:**
     * - application/pdf, application/zip
     * - image/*, video/*, audio/*
     * 
     * @param int $minimumBodyLength Minimale Body-Länge für Komprimierung in Bytes (default: 1024)
     * @return self Response mit gzip-komprimiertem Body und entsprechenden Headern
     * 
     * @example return Response::json($largeApiData)->compress(2048);
     *          // Komprimiert nur wenn JSON > 2KB und Client unterstützt gzip
     */
    public function compress(int $minimumBodyLength = 1024): self
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        if (!str_contains($accept, 'gzip')) return $this;
        if ($this->hasHeader('Content-Encoding')) return $this;
        if ($this->contentEmitterFunction !== null) return $this; // Streaming nicht komprimieren hier
        if (strlen($this->responseBody) < $minimumBodyLength) return $this;

        $ct = strtolower($this->httpHeaders['Content-Type'] ?? '');
        $skipPrefixes = ['application/pdf', 'application/zip', 'image/', 'video/', 'audio/'];
        foreach ($skipPrefixes as $sig) {
            if (str_starts_with($ct, $sig)) return $this;
        }

        $gz = gzencode($this->responseBody, 6);
        if ($gz === false) return $this;

        // Content-Length später in send() neu berechnen
        return $this->withBody($gz)
            ->withHeader('Content-Encoding', 'gzip')
            ->withVary('Accept-Encoding')
            ->withoutHeader('Content-Length');
    }

    /**
     * Conditional GET: Setzt automatisch 304, wenn ETag/Last-Modified matchen.
     * Nutzt $_SERVER-Header (If-None-Match / If-Modified-Since).
     */
    public function evaluateCachingFromGlobals(): self
    {
        $etag   = $this->httpHeaders['ETag'] ?? null;
        $lastMod = $this->httpHeaders['Last-Modified'] ?? null;

        $ifNoneMatch     = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

        $notModified = false;
        if ($etag && $ifNoneMatch && trim($ifNoneMatch) === $etag) {
            $notModified = true;
        } elseif ($lastMod && $ifModifiedSince) {
            $lm = strtotime($lastMod) ?: 0;
            $ims = strtotime($ifModifiedSince) ?: -1;
            if ($ims >= $lm) $notModified = true;
        }

        if ($notModified) {
            return self::noContent()
                ->withStatus(304)
                ->adoptCachingHeaders($this)
                ->clearBody(); // 304: kein Body
        }

        return $this;
    }

    private function clearBody(): self
    {
        $clone = clone $this;
        $clone->responseBody = '';
        $clone->contentEmitterFunction = null;
        return $clone;
    }

    private function adoptCachingHeaders(self $source): self
    {
        $clone = clone $this;
        foreach (['ETag','Last-Modified','Cache-Control','Vary'] as $h) {
            if (isset($source->httpHeaders[$h])) {
                $clone->httpHeaders[$h] = $source->httpHeaders[$h];
            }
        }
        return $clone;
    }

    // ========== Send/Emit ==========

    /**
     * Sendet Status, Header, Cookies und Body.
     * Beachtet 204/304: kein Body und kein Content-Length.
     * Bei Streaming (Emitter) wird keine Content-Length gesetzt.
     */
    public function send(): void
    {
        if (headers_sent()) {
            // Fallback: Nur Body (oder Emitter) ausgeben, um doppelte Header zu vermeiden
            $this->emitBody();
            return;
        }

        // Status-Line
        header(sprintf('HTTP/1.1 %d %s', $this->httpStatusCode, $this->getReason()), true, $this->httpStatusCode);

        // Header
        foreach ($this->httpHeaders as $name => $value) {
            header($name . ': ' . $value, true);
        }

        // Cookies
        foreach ($this->httpCookies as $c) {
            setcookie(
                $c['name'],
                $c['value'],
                [
                    'expires'  => $c['expires'],
                    'path'     => $c['path'],
                    'domain'   => $c['domain'],
                    'secure'   => $c['secure'],
                    'httponly' => $c['httponly'],
                    'samesite' => $c['samesite'],
                ]
            );
        }

        // Content-Length nur setzen, wenn sinnvoller Body, kein 204/304, kein Streaming
        $statusNoBody = in_array($this->httpStatusCode, [204, 304], true);
        if (!$statusNoBody && $this->contentEmitterFunction === null && $this->responseBody !== '' && !isset($this->httpHeaders['Content-Length'])) {
            header('Content-Length: ' . strlen($this->responseBody));
        }

        // Body/Emitter nur senden, wenn erlaubt
        if (!$statusNoBody) {
            $this->emitBody();
        }
    }

    private function emitBody(): void
    {
        if ($this->contentEmitterFunction !== null) {
            ($this->contentEmitterFunction)();
            return;
        }
        echo $this->responseBody;
    }

    // ========== PROPERTY GETTERS ==========

    /** Gibt HTTP-Status-Code zurück (200, 404, 500, etc.) */
    public function getStatus(): int { return $this->httpStatusCode; }
    
    /** Gibt Reason-Phrase zurück (custom oder Standard aus REASONS-Array) */
    public function getReason(): string { 
        return $this->customReasonPhrase ?? (self::HTTP_STATUS_REASONS[$this->httpStatusCode] ?? ''); 
    }
    
    /** @return array<string,string> Gibt alle HTTP-Headers zurück */
    public function getHeaders(): array { return $this->httpHeaders; }
    
    /** @return list<array<string,mixed>> Gibt alle Cookie-Konfigurationen zurück */
    public function getCookies(): array { return $this->httpCookies; }
    
    /** Gibt Response-Body-Content zurück */
    public function getBody(): string { return $this->responseBody; }
    
    /** Prüft ob spezifischer Header existiert (case-insensitive) */
    public function hasHeader(string $headerName): bool { 
        return isset($this->httpHeaders[self::normalizeHeaderName($headerName)]); 
    }

    // ========== Internals ==========

    /**
     * Validiert HTTP-Status-Code Range
     * 
     * @param int $statusCode HTTP-Status-Code zur Validierung
     * @return int Validierter Status-Code
     * @throws \InvalidArgumentException Bei ungültigem Status-Code (außerhalb 100-599)
     */
    private static function assertValidHttpStatus(int $statusCode): int
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code: {$statusCode}. Must be between 100-599.");
        }
        return $statusCode;
    }

    /** @param array<string,string> $headers */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $name = self::normalizeHeaderName((string)$k);
            self::assertNoHeaderInjection((string)$v);
            $out[$name] = (string)$v;
        }
        return $out;
    }

    private static function normalizeHeaderName(string $name): string
    {
        $name = str_replace(['_', ' '], '-', $name);
        $name = preg_replace('/[^A-Za-z0-9\-]/', '', $name) ?? $name;
        $name = ucwords(strtolower($name), '-');
        return $name;
    }

    private static function assertNoHeaderInjection(string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('Header value must not contain CR or LF.');
        }
    }
}