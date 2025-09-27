<?php

declare(strict_types=1);

namespace Core;

abstract class Controller
{
    protected Request $request;
    protected Response $response;

    protected ?FlashBag $flash = null;
    protected ?UrlGenerator $urls = null;

    /** Performance-Optimierungen */
    private ?string $cachedRequestId = null;
    private ?array $cachedJsonBody = null;
    private bool $debug = false;

    public function __construct(
        Request $request,
        Response $response,
        ?FlashBag $flash = null,
        ?UrlGenerator $urls = null
    ) {
        $this->request  = $request;
        $this->response = $response;
        $this->flash    = $flash;
        $this->urls     = $urls;

        $this->debug = (($_ENV['APP_DEBUG'] ?? false) || ($_ENV['APP_ENV'] ?? '') === 'development');
    }

    // ── Content Negotiation ─────────────────────────────────────────────────────

    protected function isApiRequest(): bool
    {
        $acceptHeader   = strtolower($this->getServerValue('HTTP_ACCEPT'));
        $requestUri     = $this->getServerValue('REQUEST_URI');
        $contentType    = strtolower($this->getServerValue('CONTENT_TYPE') ?: $this->getServerValue('HTTP_CONTENT_TYPE'));
        $xmlHttpRequest = strtolower($this->getServerValue('HTTP_X_REQUESTED_WITH'));

        if (str_starts_with($requestUri, '/api/') || str_contains($requestUri, '/api/')) {
            return true;
        }
        if (
            str_contains($contentType, 'application/json') ||
            str_contains($acceptHeader, 'application/vnd.api+json') ||
            str_contains($acceptHeader, '+json') // z. B. application/problem+json
        ) {
            return true;
        }
        if ($xmlHttpRequest === 'xmlhttprequest') {
            return true;
        }

        // Einfache q-weighted Prüfung, ohne volle RFC-Komplexität
        $best = $this->negotiateBestContentType($acceptHeader, ['application/json', 'text/html']);
        return $best === 'application/json';
    }

    protected function wantsJson(): bool
    {
        return $this->isApiRequest();
    }

    protected function acceptsJson(): bool
    {
        return $this->isApiRequest();
    }

    protected function acceptsHtml(): bool
    {
        return !$this->isApiRequest();
    }

    private function negotiateBestContentType(string $acceptHeader, array $availableContentTypes): string
    {
        // Beispiel: "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8"
        $acceptHeaderParts = array_map('trim', explode(',', $acceptHeader));
        $contentTypeScores = [];

        foreach ($acceptHeaderParts as $acceptHeaderPart) {
            [$mediaType, $qualityPart] = array_pad(explode(';q=', $acceptHeaderPart, 2), 2, '1.0');
            $mediaType = trim($mediaType);
            $qualityValue = (float) trim($qualityPart);

            foreach ($availableContentTypes as $candidateContentType) {
                if ($mediaType === $candidateContentType || $mediaType === '*/*') {
                    $contentTypeScores[$candidateContentType] = max($contentTypeScores[$candidateContentType] ?? 0.0, $qualityValue);
                } elseif (str_ends_with($mediaType, '/*')) {
                    $mediaTypePrefix = substr($mediaType, 0, (int)strpos($mediaType, '/'));
                    if (str_starts_with($candidateContentType, $mediaTypePrefix . '/')) {
                        $contentTypeScores[$candidateContentType] = max($contentTypeScores[$candidateContentType] ?? 0.0, $qualityValue);
                    }
                }
            }
        }

        arsort($contentTypeScores);
        return array_key_first($contentTypeScores) ?: $availableContentTypes[0];
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
        if (method_exists($this->request, 'get')) {
            /** @var mixed $page */
            $page = $this->request->{'get'}('page');
        } else {
            $page = $_GET['page'] ?? $_POST['page'] ?? null;
        }
        return max(1, (int)($page ?? $defaultPage));
    }

    protected function getPerPage(int $defaultItemsPerPage = 20, int $maxItemsPerPage = 100): int
    {
        if (method_exists($this->request, 'get')) {
            /** @var mixed $perPageValue */
            $perPageValue = $this->request->{'get'}('per_page');
            /** @var mixed $limitValue */
            $limitValue = $this->request->{'get'}('limit');
        } else {
            $perPageValue = $_GET['per_page'] ?? $_POST['per_page'] ?? null;
            $limitValue = $_GET['limit'] ?? $_POST['limit'] ?? null;
        }

        $requestedItemsPerPage = (int)($perPageValue ?? $limitValue ?? $defaultItemsPerPage);
        return max(1, min($maxItemsPerPage, $requestedItemsPerPage));
    }

    // ── Flash ───────────────────────────────────────────────────────────────────

    protected function flashSuccess(?string $message = null): ?string { return $message ? $this->flash?->success($message) : $this->flash?->pull('success'); }
    protected function flashError(?string $message = null): ?string   { return $message ? $this->flash?->error($message)   : $this->flash?->pull('error'); }
    protected function flashWarning(?string $message = null): ?string { return $message ? $this->flash?->warning($message) : $this->flash?->pull('warning'); }
    protected function flashInfo(?string $message = null): ?string    { return $message ? $this->flash?->info($message)    : $this->flash?->pull('info'); }

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

        $viewData = $data + ['request' => $this->request] + $this->flashContext();
        $viewResponse = View::make($template, $viewData);

        if (!is_object($viewResponse)) {
            $viewResponse = $this->response->html((string)$viewResponse);
        }

        return $status !== 200 ? $viewResponse->withStatus($status) : $viewResponse;
    }

    protected function partial(string $template, array $data = [], int $status = 200): Response
    {
        $partialHtml = View::renderPartial($template, $data + $this->flashContext());
        $partialResponse = $this->response->html($partialHtml);

        return $status !== 200 ? $partialResponse->withStatus($status) : $partialResponse;
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
        $errorResponseBody = [
            'success' => false,
            'error'   => [
                'message' => $message,
                'code'    => $status,
            ],
            'meta'    => $this->baseMeta(),
        ];

        if ($this->debug && $details) {
            $errorResponseBody['error']['details'] = $details;
        }

        return $this->response->json($errorResponseBody, $status);
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
        return array_filter([
            'timestamp'  => time(),
            'request_id' => $this->getRequestId(),
            'method'     => $this->getHttpMethod(),
            'path'       => $this->debug ? $this->getServerValue('REQUEST_URI') : null,
        ], static fn($v) => $v !== null);
    }

    private function getRequestId(): string
    {
        if ($this->cachedRequestId) {
            return $this->cachedRequestId;
        }

        if (method_exists($this->request, 'id')) {
            try {
                /** @var mixed $requestIdValue */
                $requestIdValue = $this->request->{'id'}();
                if (is_string($requestIdValue) && $requestIdValue !== '') {
                    return $this->cachedRequestId = $requestIdValue;
                }
            } catch (\Throwable) {
                // Fallback
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
        $referrerUrl = $this->getServerValue('HTTP_REFERER', $fallbackUrl);
        return $this->redirect($referrerUrl);
    }

    protected function redirectToRoute(string $name, array $params = [], int $status = 302): Response
    {
        $redirectUrl = $this->urls?->route($name, $params)
            ?? ('/' . ltrim($name, '/') . (empty($params) ? '' : '?' . http_build_query($params)));
        return $this->redirect($redirectUrl, $status);
    }

    private function sanitizeRedirect(string $url): string
    {
        // Header-Injection verhindern
        $sanitizedUrl = str_replace(["\r", "\n"], '', $url);

        // Open Redirects verhindern: nur relative Pfade zulassen
        if (preg_match('#^\s*(https?://|//)#i', $sanitizedUrl)) {
            return '/';
        }
        return $sanitizedUrl === '' ? '/' : $sanitizedUrl;
    }

    protected function success(string $message, ?string $destinationUrl = null, int $status = 302): Response
    {
        $this->flashSuccess($message);
        return $destinationUrl ? $this->redirect($destinationUrl, $status) : $this->back();
    }

    protected function error(string $message, ?string $destinationUrl = null, int $status = 302): Response
    {
        $this->flashError($message);
        return $destinationUrl ? $this->redirect($destinationUrl, $status) : $this->back();
    }

    // ── Validation ──────────────────────────────────────────────────────────────

    /**
     * Validiert Eingaben anhand einfacher Regeln.
     * Beispiel:
     * $data = $this->validateInput([
     *   'name'  => 'required|string|min:2|max:50',
     *   'email' => 'required|email',
     *   'role'  => 'in:user,admin',
     * ]);
     */
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
        // Bevorzugt die Request-Abstraktion
        $requestData = [];
        if (method_exists($this->request, 'all')) {
            try {
                /** @var mixed $allRequestData */
                $allRequestData = $this->request->{'all'}();
                $requestData = is_array($allRequestData) ? $allRequestData : [];
            } catch (\Throwable) {
                $requestData = array_merge($_GET ?? [], $_POST ?? []);
            }
        } else {
            $requestData = array_merge($_GET ?? [], $_POST ?? []);
        }

        // JSON-Body einmalig lesen & ergänzen (nicht überschreiben)
        if ($this->cachedJsonBody === null) {
            $this->cachedJsonBody = [];
            $contentTypeHeader = strtolower($this->getServerValue('CONTENT_TYPE') ?: $this->getServerValue('HTTP_CONTENT_TYPE'));
            if (str_contains($contentTypeHeader, 'application/json')) {
                $rawJsonInput = file_get_contents('php://input') ?: '';
                $decodedJsonData = json_decode($rawJsonInput, true);
                if (is_array($decodedJsonData)) {
                    $this->cachedJsonBody = $decodedJsonData;
                }
            }
        }

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

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $sessionValue = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $sessionValue;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }
}

final class FlashBag
{
    public function __construct(private SessionStore $session) {}

    public function set(string $type, string|array $message): void
    {
        $this->session->put("flash_{$type}", $message);
    }

    public function add(string $type, string $message): void
    {
        $flashKey = "flash_{$type}";
        $currentMessages = $this->session->get($flashKey, []);
        $currentMessages = is_array($currentMessages) ? $currentMessages : [$currentMessages];
        $currentMessages[] = $message;
        $this->session->put($flashKey, $currentMessages);
    }

    public function peek(string $type): mixed
    {
        return $this->session->get("flash_{$type}");
    }

    public function pull(string $type): mixed
    {
        return $this->session->pull("flash_{$type}");
    }

    public function success(string $message): string { $this->set('success', $message); return $message; }
    public function error(string $message): string   { $this->set('error',   $message); return $message; }
    public function warning(string $message): string { $this->set('warning', $message); return $message; }
    public function info(string $message): string    { $this->set('info',    $message); return $message; }
}

/**
 * Minimal-Validator mit praxisnahen Regeln.
 * Unterstützt: required, string, email, boolean, integer, array, min:N, max:N, in:a,b,c
 */
final class SimpleValidator
{
    /** @var array<string,string> */
    private array $validationRules;
    /** @var array<string,mixed> */
    private array $inputData;
    /** @var array<string,string[]> */
    private array $validationErrors = [];
    /** @var array<string,string[]> */
    private static array $ruleCache = [];

    /**
     * @param array<string,string> $validationRules
     * @param array<string,mixed>  $inputData
     */
    public function __construct(array $validationRules, array $inputData)
    {
        $this->validationRules = $validationRules;
        $this->inputData = $inputData;
    }

    public function passes(): bool
    {
        foreach ($this->validationRules as $fieldName => $rulesString) {
            $fieldValue = $this->inputData[$fieldName] ?? null;

            foreach ($this->parseRules($rulesString) as $validationRule) {
                if ($validationRule === 'required' && ($fieldValue === null || $fieldValue === '')) {
                    $this->addValidationError($fieldName, 'is required');
                } elseif ($validationRule === 'string' && $fieldValue !== null && !is_string($fieldValue)) {
                    $this->addValidationError($fieldName, 'must be a string');
                } elseif ($validationRule === 'email' && $fieldValue !== null && !filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                    $this->addValidationError($fieldName, 'must be a valid email');
                } elseif ($validationRule === 'boolean' && $fieldValue !== null && !is_bool(filter_var($fieldValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    $this->addValidationError($fieldName, 'must be a boolean');
                } elseif ($validationRule === 'integer' && $fieldValue !== null && filter_var($fieldValue, FILTER_VALIDATE_INT) === false) {
                    $this->addValidationError($fieldName, 'must be an integer');
                } elseif ($validationRule === 'array' && $fieldValue !== null && !is_array($fieldValue)) {
                    $this->addValidationError($fieldName, 'must be an array');
                } elseif (str_starts_with($validationRule, 'min:') && is_string($fieldValue)) {
                    $minLength = (int)substr($validationRule, 4);
                    if (mb_strlen($fieldValue) < $minLength) {
                        $this->addValidationError($fieldName, "must be at least {$minLength} characters");
                    }
                } elseif (str_starts_with($validationRule, 'max:') && is_string($fieldValue)) {
                    $maxLength = (int)substr($validationRule, 4);
                    if (mb_strlen($fieldValue) > $maxLength) {
                        $this->addValidationError($fieldName, "must be at most {$maxLength} characters");
                    }
                } elseif (str_starts_with($validationRule, 'in:')) {
                    $allowedValues = array_map('trim', explode(',', substr($validationRule, 3)));
                    if ($fieldValue !== null && !in_array((string)$fieldValue, $allowedValues, true)) {
                        $this->addValidationError($fieldName, 'is not an allowed value');
                    }
                }
            }
        }

        return $this->validationErrors === [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->validationErrors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        $validatedData = [];
        foreach (array_keys($this->validationRules) as $fieldKey) {
            if (array_key_exists($fieldKey, $this->inputData)) {
                $validatedData[$fieldKey] = $this->inputData[$fieldKey];
            }
        }
        return $validatedData;
    }

    private function addValidationError(string $fieldName, string $errorMessage): void
    {
        $this->validationErrors[$fieldName][] = "{$fieldName} {$errorMessage}";
    }

    /** @return string[] */
    private function parseRules(string $rulesString): array
    {
        return self::$ruleCache[$rulesString] ??= array_map('trim', explode('|', $rulesString));
    }
}

// ── Exceptions für Flows ───────────────────────────────────────────────────────

final class RedirectHttpException extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('Redirect');
    }
}

final class ValidationHttpException extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('Validation failed');
    }
}