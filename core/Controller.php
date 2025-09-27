<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    protected Request $request;
    protected Response $response;

    protected ?FlashBag $flash = null;
    protected ?UrlGenerator $urls = null;

    /** Performance-optimiert: Cache für Request-Body und Request-ID */
    private ?string $cachedRequestId = null;
    private ?array $cachedJsonBody = null;

    public function __construct(Request $request, Response $response, ?FlashBag $flash = null, ?UrlGenerator $urls = null)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->flash    = $flash;
        $this->urls     = $urls;
    }

    // ── Content Negotiation ─────────────────────────────────────────────────────

    protected function isApiRequest(): bool
    {
        $acceptHeader = strtolower($this->getServerValue('HTTP_ACCEPT'));
        $requestUri = $this->getServerValue('REQUEST_URI');
        $contentType = strtolower($this->getServerValue('CONTENT_TYPE') ?: $this->getServerValue('HTTP_CONTENT_TYPE'));
        $xmlHttpRequest = strtolower($this->getServerValue('HTTP_X_REQUESTED_WITH'));

        if (str_starts_with($requestUri, '/api/') || str_contains($requestUri, '/api/')) {
            return true;
        }
        if (str_contains($contentType, 'application/json') || str_contains($acceptHeader, 'application/vnd.api+json')) {
            return true;
        }
        if ($xmlHttpRequest === 'xmlhttprequest') {
            return true;
        }

        // Einfache q-weighted Prüfung, ohne volle RFC-Parser-Komplexität
        $bestContentType = $this->negotiateBestContentType($acceptHeader, ['application/json', 'text/html']);
        return $bestContentType === 'application/json';
    }

    protected function wantsJson(): bool
    {
        return $this->isApiRequest();
    }

    private function negotiateBestContentType(string $acceptHeader, array $availableContentTypes): string
    {
        // Beispiel: "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8"
        $acceptHeaderParts = array_map('trim', explode(',', $acceptHeader));
        $qualityScores = [];
        
        foreach ($acceptHeaderParts as $acceptPart) {
            [$mediaType, $qualityValue] = array_pad(explode(';q=', $acceptPart), 2, '1.0');
            $mediaType = trim($mediaType);
            $qualityValue = (float)$qualityValue;
            
            foreach ($availableContentTypes as $candidateType) {
                if ($mediaType === $candidateType || $mediaType === '*/*') {
                    $qualityScores[$candidateType] = max($qualityScores[$candidateType] ?? 0.0, $qualityValue);
                } elseif (str_ends_with($mediaType, '/*') && str_starts_with($candidateType, substr($mediaType, 0, strpos($mediaType, '/')))) {
                    $qualityScores[$candidateType] = max($qualityScores[$candidateType] ?? 0.0, $qualityValue);
                }
            }
        }
        
        arsort($qualityScores);
        return array_key_first($qualityScores) ?: $availableContentTypes[0];
    }

    protected function getHttpMethod(): string
    {
        return strtoupper($this->getServerValue('REQUEST_METHOD') ?: 'GET');
    }

    protected function getServerValue(string $serverKey, string $defaultValue = ''): string
    {
        $serverValue = $_SERVER[$serverKey] ?? $defaultValue;
        return is_string($serverValue) ? $serverValue : $defaultValue;
    }

    // ── Pagination ──────────────────────────────────────────────────────────────

    protected function getPage(int $defaultPage = 1): int
    {
        $pageParameter = null;
        if (method_exists($this->request, 'get')) {
            /** @var mixed $pageParameter */
            $pageParameter = $this->request->{'get'}('page'); // Dynamischer Aufruf
        } else {
            $pageParameter = $_GET['page'] ?? $_POST['page'] ?? null;
        }
        return max(1, (int)($pageParameter ?? $defaultPage));
    }

    protected function getPerPage(int $defaultItemsPerPage = 20, int $maxItemsPerPage = 100): int
    {
        $perPageParameter = null;
        $limitParameter = null;
        
        if (method_exists($this->request, 'get')) {
            /** @var mixed $perPageParameter */
            $perPageParameter = $this->request->{'get'}('per_page');
            /** @var mixed $limitParameter */
            $limitParameter = $this->request->{'get'}('limit');
        } else {
            $perPageParameter = $_GET['per_page'] ?? $_POST['per_page'] ?? null;
            $limitParameter = $_GET['limit'] ?? $_POST['limit'] ?? null;
        }
        
        $requestedItemsPerPage = (int)($perPageParameter ?? $limitParameter ?? $defaultItemsPerPage);
        return max(1, min($maxItemsPerPage, $requestedItemsPerPage));
    }

    // ── Flash ───────────────────────────────────────────────────────────────────

    protected function flashSuccess(?string $msg = null): ?string    { return $msg ? $this->flash?->success($msg) : $this->flash?->pull('success'); }
    protected function flashError(?string $msg = null): ?string      { return $msg ? $this->flash?->error($msg)   : $this->flash?->pull('error'); }
    protected function flashWarning(?string $msg = null): ?string    { return $msg ? $this->flash?->warning($msg) : $this->flash?->pull('warning'); }
    protected function flashInfo(?string $msg = null): ?string       { return $msg ? $this->flash?->info($msg)    : $this->flash?->pull('info'); }

    protected function flashContext(): array
    {
        return [
            'flash_success' => $this->flash?->peek('success'),
            'flash_error'   => $this->flash?->peek('error'),
            'flash_warning' => $this->flash?->peek('warning'),
            'flash_info'    => $this->flash?->peek('info'),
        ];
    }

    // ── View/Partial ────────────────────────────────────────────────────────────

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        if ($this->wantsJson()) {
            return $this->apiError('HTML not acceptable for this endpoint.', 406);
        }
        $payload = $data + ['request' => $this->request] + $this->flashContext();
        $resp = View::make($template, $payload);
        return $status !== 200 ? $resp->withStatus($status) : $resp;
    }

    protected function partial(string $template, array $data = [], int $status = 200): Response
    {
        $html = View::renderPartial($template, $data + $this->flashContext());
        $resp = $this->response->html($html);
        return $status !== 200 ? $resp->withStatus($status) : $resp;
    }

    // ── JSON ────────────────────────────────────────────────────────────────────

    protected function apiSuccess(mixed $data = null, array $meta = [], int $status = 200): Response
    {
        return $this->response->json([
            'success' => true,
            'data'    => $data,
            'meta'    => $this->baseMeta() + $meta,
        ], $status);
    }

    protected function apiError(string $message, int $status = 400, array $details = []): Response
    {
        $body = [
            'success' => false,
            'error'   => [
                'message' => $message,
                'code'    => $status,
            ],
            'meta'    => $this->baseMeta(),
        ];

        $debug = (($_ENV['APP_DEBUG'] ?? false) || ($_ENV['APP_ENV'] ?? '') === 'development');
        if ($debug && $details) {
            $body['error']['details'] = $details;
        }

        return $this->response->json($body, $status);
    }

    protected function apiValidationError(array $errors): Response
    {
        return $this->apiError('Validation failed', 422, ['validation_errors' => $errors]);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return $this->response->json($data, $status);
    }

    protected function baseMeta(): array
    {
        $isDebugMode = (($_ENV['APP_DEBUG'] ?? false) || ($_ENV['APP_ENV'] ?? '') === 'development');

        return array_filter([
            'timestamp'  => time(),
            'request_id' => $this->getRequestId(),
            'method'     => $this->getHttpMethod(),
            'path'       => $isDebugMode ? $this->getServerValue('REQUEST_URI') : null,
        ], static fn($value) => $value !== null);
    }

    private function getRequestId(): string
    {
        if ($this->cachedRequestId) {
            return $this->cachedRequestId;
        }
        // Wenn dein Request bereits eine ID hat, benutze sie
        if (method_exists($this->request, 'id')) {
            try {
                /** @var mixed $requestIdFromRequest */
                $requestIdFromRequest = $this->request->{'id'}(); // Dynamischer Aufruf für Linter
                if (is_string($requestIdFromRequest) && $requestIdFromRequest !== '') {
                    return $this->cachedRequestId = $requestIdFromRequest;
                }
            } catch (\Throwable) {
                // Fallback bei Fehlern
            }
        }
        return $this->cachedRequestId = bin2hex(random_bytes(8));
    }

    // ── Redirects ───────────────────────────────────────────────────────────────

    protected function redirect(string $url, int $status = 302): Response
    {
        return $this->response->redirect($this->sanitizeRedirect($url), $status);
    }

    protected function back(string $fallbackUrl = '/'): Response
    {
        $refererUrl = $this->getServerValue('HTTP_REFERER', $fallbackUrl);
        return $this->redirect($refererUrl);
    }

    protected function redirectToRoute(string $name, array $params = [], int $status = 302): Response
    {
        $url = $this->urls?->route($name, $params)
            ?? ('/' . ltrim($name, '/') . (empty($params) ? '' : '?' . http_build_query($params)));
        return $this->redirect($url, $status);
    }

    private function sanitizeRedirect(string $url): string
    {
        // Verhindert Open Redirects: erlaube nur relative Pfade
        if (preg_match('#^\s*(https?://|//)#i', $url)) {
            return '/';
        }
        return $url === '' ? '/' : $url;
    }

    protected function success(string $message, ?string $to = null, int $status = 302): Response
    {
        $this->flashSuccess($message);
        return $to ? $this->redirect($to, $status) : $this->back();
    }

    protected function error(string $message, ?string $to = null, int $status = 302): Response
    {
        $this->flashError($message);
        return $to ? $this->redirect($to, $status) : $this->back();
    }

    // ── Validation ──────────────────────────────────────────────────────────────

    protected function validateInput(array $validationRules, ?array $inputData = null): array
    {
        $requestPayload = $inputData ?? $this->collectRequestPayloadOnce();

        $inputValidator = new SimpleValidator($validationRules, $requestPayload);

        if ($inputValidator->passes()) {
            return $inputValidator->validated();
        }

        if ($this->wantsJson()) {
            throw new ValidationHttpException($this->apiValidationError($inputValidator->errors()));
        }

        $this->flashError('Bitte Eingaben prüfen.');
        $this->flash?->set('validation_errors', $inputValidator->errors());
        throw new RedirectHttpException($this->back());
    }

    private function collectRequestPayloadOnce(): array
    {
        // Bevorzuge Request-Abstraktion falls verfügbar, sonst Fallback
        $requestData = [];
        if (method_exists($this->request, 'all')) {
            try {
                /** @var mixed $allRequestData */
                $allRequestData = $this->request->{'all'}(); // Dynamischer Aufruf für Linter
                $requestData = is_array($allRequestData) ? $allRequestData : [];
            } catch (\Throwable) {
                // Fallback auf $_GET + $_POST
                $requestData = array_merge($_GET ?? [], $_POST ?? []);
            }
        } else {
            // Direkter Fallback auf Superglobals
            $requestData = array_merge($_GET ?? [], $_POST ?? []);
        }

        // JSON nur einmal lesen & mergen (ohne vorhandene Keys zu überschreiben)
        if ($this->cachedJsonBody === null) {
            $this->cachedJsonBody = [];
            $contentType = strtolower($this->getServerValue('CONTENT_TYPE') ?: $this->getServerValue('HTTP_CONTENT_TYPE'));
            if (str_contains($contentType, 'application/json')) {
                $rawJsonInput = file_get_contents('php://input') ?: '';
                $jsonData = json_decode($rawJsonInput, true);
                if (is_array($jsonData)) {
                    $this->cachedJsonBody = $jsonData;
                }
            }
        }

        // JSON ergänzt Query/Form, ersetzt sie aber nicht
        foreach ($this->cachedJsonBody as $jsonKey => $jsonValue) {
            if (!array_key_exists($jsonKey, $requestData)) {
                $requestData[$jsonKey] = $jsonValue;
            }
        }

        return $requestData;
    }
}

// ── Portale (Interfaces/Helper) ────────────────────────────────────────────────

interface UrlGenerator
{
    public function route(string $name, array $params = []): string;
}

interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value): void;
    public function pull(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
}

final class PhpSessionStore implements SessionStore
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
    public function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public function put(string $key, mixed $value): void           { $_SESSION[$key] = $value; }
    public function pull(string $key, mixed $default = null): mixed
    {
        $val = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $val;
    }
    public function has(string $key): bool { return array_key_exists($key, $_SESSION); }
}

final class FlashBag
{
    public function __construct(private SessionStore $session) {}

    public function set(string $type, string|array $message): void { $this->session->put("flash_{$type}", $message); }
    public function add(string $type, string $message): void
    {
        $key = "flash_{$type}";
        $cur = $this->session->get($key, []);
        $cur = is_array($cur) ? $cur : [$cur];
        $cur[] = $message;
        $this->session->put($key, $cur);
    }
    public function peek(string $type): mixed { return $this->session->get("flash_{$type}"); }
    public function pull(string $type): mixed { return $this->session->pull("flash_{$type}"); }

    public function success(string $m): string { $this->set('success', $m); return $m; }
    public function error(string $m): string   { $this->set('error',   $m); return $m; }
    public function warning(string $m): string { $this->set('warning', $m); return $m; }
    public function info(string $m): string    { $this->set('info',    $m); return $m; }
}

/**
 * Minimal-Validator mit nützlichen Grundregeln.
 */
final class SimpleValidator
{
    /** @var array<string,string> */
    private array $rules;
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,string[]> */
    private array $errors = [];
    /** @var array<string,string[]> */
    private static array $ruleCache = [];

    public function __construct(array $rules, array $data)
    {
        $this->rules = $rules;
        $this->data  = $data;
    }

    public function passes(): bool
    {
        foreach ($this->rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            foreach ($this->parse($ruleStr) as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->push($field, 'is required');
                } elseif ($rule === 'string' && $value !== null && !is_string($value)) {
                    $this->push($field, 'must be a string');
                } elseif ($rule === 'email' && $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->push($field, 'must be a valid email');
                } elseif ($rule === 'boolean' && $value !== null && !is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    $this->push($field, 'must be a boolean');
                } elseif ($rule === 'integer' && $value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->push($field, 'must be an integer');
                } elseif ($rule === 'array' && $value !== null && !is_array($value)) {
                    $this->push($field, 'must be an array');
                } elseif (str_starts_with($rule, 'min:') && is_string($value)) {
                    $min = (int)substr($rule, 4);
                    if (mb_strlen($value) < $min) {
                        $this->push($field, "must be at least {$min} characters");
                    }
                }
            }
        }
        return $this->errors === [];
    }

    /** @return array<string,string[]> */
    public function errors(): array { return $this->errors; }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $k) {
            if (array_key_exists($k, $this->data)) {
                $out[$k] = $this->data[$k];
            }
        }
        return $out;
    }

    private function push(string $field, string $message): void
    {
        $this->errors[$field][] = "{$field} {$message}";
    }

    /** @return string[] */
    private function parse(string $rulesString): array
    {
        return self::$ruleCache[$rulesString] ??= array_map('trim', explode('|', $rulesString));
    }
}

// ── Exceptions für Flows ───────────────────────────────────────────────────────

final class RedirectHttpException extends \RuntimeException
{
    public function __construct(public readonly Response $response) { parent::__construct('Redirect'); }
}

final class ValidationHttpException extends \RuntimeException
{
    public function __construct(public readonly Response $response) { parent::__construct('Validation failed'); }
}
