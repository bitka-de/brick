<?php
/**
 * Brick Router – Production-Ready HTTP Router
 *
 * Features:
 * - Kompilierte Patterns (parametrisierte Routen + optionale Segmente)
 * - REST-Methoden (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
 * - HEAD -> GET Mapping (RFC 7231)
 * - Route-Gruppen mit Prefix + Middleware (stackbar)
 * - Globale + Routen + Gruppen-Middleware (einheitliche Signatur)
 * - Automatisches 404 / 405 inkl. Allow-Header, OPTIONS-Handler
 * - Exact-Hit-Map für statische Routen (Performance)
 * - Keine Dependencies, pures PHP
 */

declare(strict_types=1);

namespace Core;

final class Router
{
    /**
     * Struktur einer Route:
     * [
     *   'method'     => 'GET',
     *   'pattern'    => '/users/{id}',
     *   'regex'      => '~^/users/([^/]+)$~',
     *   'params'     => ['id'],
     *   'callback'   => callable(Request $req, array $params): Response,
     *   'middleware' => callable[] // fn(Request $req, array $params, callable $next): Response
     * ]
     *
     * @var array<string, array<int, array>>
     */
    private array $routes = [];

    /** @var array<string, array<int, array>> Exact statische Treffer pro Methode (pattern === path) */
    private array $staticRoutes = [];

    /** @var callable[] Globale Middleware */
    private array $globalMiddleware = [];

    /**
     * Gruppen-Stack, um Verschachtelung zu ermöglichen.
     * Jeder Stack-Eintrag: ['prefix' => '/api', 'middleware' => callable[]]
     * @var array<int, array{prefix:string, middleware:array}>
     */
    private array $groupStack = [];

    // ---------- Public API ----------

    public function get(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $callback, $middleware);
    }

    public function post(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $callback, $middleware);
    }

    public function put(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->add('PUT', $pattern, $callback, $middleware);
    }

    public function delete(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->add('DELETE', $pattern, $callback, $middleware);
    }

    public function patch(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->add('PATCH', $pattern, $callback, $middleware);
    }

    /**
     * Eine Route für mehrere Methoden registrieren.
     * @param string[] $methods
     */
    public function match(array $methods, string $pattern, callable $callback, array $middleware = []): self
    {
        foreach ($methods as $m) {
            $this->add(strtoupper($m), $pattern, $callback, $middleware);
        }
        return $this;
    }

    /** Für alle gängigen Methoden registrieren (HEAD/OPTIONS handled automatisch über Router) */
    public function any(string $pattern, callable $callback, array $middleware = []): self
    {
        return $this->match(['GET','POST','PUT','PATCH','DELETE'], $pattern, $callback, $middleware);
    }

    /** Globale Middleware anhängen */
    public function middleware(callable $mw): self
    {
        $this->globalMiddleware[] = $mw;
        return $this;
    }

    /**
     * Gruppen mit Prefix + Middleware (verschachtelbar).
     * @param string $prefix z.B. '/api/v1'
     * @param callable(self $r): void $groupDef
     * @param callable[] $middleware
     */
    public function group(string $prefix, callable $groupDef, array $middleware = []): self
    {
        $prefix = $this->cleanPrefix($prefix);
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];
        try {
            $groupDef($this);
        } finally {
            array_pop($this->groupStack);
        }
        return $this;
    }

    /**
     * Dispatcher: nimmt einen Request und liefert immer eine Response.
     * - 404 Not Found, wenn keine Route passt
     * - 405 Method Not Allowed mit Allow-Header, wenn Pfad existiert aber Methode nicht
     * - OPTIONS beantwortet automatisch Allow-Header für den Pfad
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $this->normalizePath($request->path());

        // OPTIONS: Immer Allow-Header für vorhandene Pfad-Matches
        if ($method === 'OPTIONS') {
            $allow = $this->allowedMethodsForPath($path);
            return Response::noContent(204)->withHeader('Allow', $allow);
        }

        // HEAD -> GET mappen (gleiche Route-Logik)
        $lookupMethod = ($method === 'HEAD') ? 'GET' : $method;

        // 1) Exact static match?
        if (isset($this->staticRoutes[$lookupMethod][$path])) {
            $route = $this->staticRoutes[$lookupMethod][$path];
            return $this->runRoute($route, $request, []);
        }

        // 2) Regex matches
        if (!empty($this->routes[$lookupMethod])) {
            foreach ($this->routes[$lookupMethod] as $route) {
                if (preg_match($route['regex'], $path, $m)) {
                    // Param-Werte mappen
                    $params = [];
                    foreach ($route['params'] as $i => $name) {
                        // +1 wegen full match an Index 0
                        $params[$name] = $m[$i + 1] ?? null;
                    }
                    $resp = $this->runRoute($route, $request, $params);

                    // Für HEAD formell keinen Body senden (wenn Response API das zulässt)
                    if ($method === 'HEAD') {
                        // Falls deine Response-Klasse ein leeres Body-Helper hat:
                        // return $resp->withBody('');
                        return $resp; // konservativ: Body ignorieren
                    }
                    return $resp;
                }
            }
        }

        // 3) Pfad existiert mit anderer Methode? -> 405
        $allow = $this->allowedMethodsForPath($path);
        if ($allow !== 'OPTIONS') {
            return $this->methodNotAllowed($allow);
        }

        // 4) Nichts passt -> 404
        return $this->notFound();
    }

    // ---------- Internals ----------

    private function add(string $method, string $pattern, callable $callback, array $middleware): self
    {
        [$fullPattern, $stackedMiddleware] = $this->applyGroups($pattern, $middleware);
        $fullPattern = $this->normalizePattern($fullPattern);

        // Kompilieren
        [$isStatic, $regex, $paramNames] = $this->compilePattern($fullPattern);

        $route = [
            'method'     => $method,
            'pattern'    => $fullPattern,
            'regex'      => $regex,
            'params'     => $paramNames,
            'callback'   => $callback,
            'middleware' => $stackedMiddleware,
        ];

        if ($isStatic) {
            $this->staticRoutes[$method][$fullPattern] = $route;
        } else {
            $this->routes[$method][] = $route;
        }

        return $this;
    }

    /** Prefix + Middleware aus Gruppen auf das Pattern anwenden */
    private function applyGroups(string $pattern, array $routeMiddleware): array
    {
        $prefix = '';
        $mw = $this->globalMiddleware;

        foreach ($this->groupStack as $g) {
            $prefix .= $g['prefix'];
            if (!empty($g['middleware'])) {
                // Reihenfolge: global -> Gruppen in definierter Reihenfolge -> Route
                $mw = array_merge($mw, $g['middleware']);
            }
        }

        $mw = array_merge($mw, $routeMiddleware);
        $fullPattern = $this->concatPrefix($prefix, $pattern);

        return [$fullPattern, $mw];
    }

    /** '/api' + '/users' -> '/api/users' */
    private function concatPrefix(string $prefix, string $pattern): string
    {
        $prefix = rtrim($prefix, '/');
        $pattern = '/' . ltrim($pattern, '/');
        return ($prefix === '') ? $pattern : $prefix . $pattern;
    }

    private function cleanPrefix(string $prefix): string
    {
        if ($prefix === '' || $prefix === '/') {
            return '';
        }
        return '/' . trim($prefix, '/');
    }

    private function normalizePattern(string $pattern): string
    {
        // Root bleibt '/', alles andere ohne trailing slash
        if ($pattern === '/') {
            return '/';
        }
        return '/' . trim($pattern, '/');
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === null) {
            return '/';
        }
        $path = '/' . ltrim($path, '/');
        return ($path === '/') ? '/' : rtrim($path, '/');
    }

    /**
     * Übersetzt ein Pattern wie '/users/{id}/{slug?}' in Regex + Paramliste.
     * - {name}    -> Pflichtsegment (kein '/')
     * - {name?}   -> Optionales Segment (inkl. Slash optional)
     */
    private function compilePattern(string $pattern): array
    {
        // Statisch?
        if (!str_contains($pattern, '{')) {
            // keine Platzhalter -> static
            $regex = '~^' . preg_quote($pattern, '~') . '$~';
            return [true, $regex, []];
        }

        $paramNames = [];

        $regex = preg_replace_callback('/\{([^}]+)\}/', function(array $m) use (&$paramNames) {
            $raw = $m[1];
            $optional = str_ends_with($raw, '?');
            $name = $optional ? substr($raw, 0, -1) : $raw;
            $paramNames[] = $name;

            // Standard: ein Segment bis zum nächsten '/'
            $seg = '([^/]+)';

            // Optional einschließen inkl. voranstehendem Slash
            return $optional ? '(?:/' . $seg . ')?' : '/' . $seg;
        }, $pattern);

        // Anfang kann optionalen Slash doppeln; normalize:
        $regex = '^' . rtrim($regex, '/') . '$';
        // Startanker sicherstellen
        if ($regex[0] !== '^') {
            $regex = '^' . $regex;
        }

        // Sicheres Delimiter-Regex
        $regex = '~' . str_replace('~', '\~', $regex) . '~';

        // Wenn das Pattern am Anfang kein statisches Segment hat, kann doppelter Slash entstehen -> tolerieren wir.
        return [false, $regex, $paramNames];
    }

    /** Ermittelt erlaubte Methoden für einen Pfad (inkl. HEAD für GET, immer OPTIONS) */
    private function allowedMethodsForPath(string $path): string
    {
        $methods = [];

        // statische Kandidaten
        foreach ($this->staticRoutes as $method => $map) {
            if (isset($map[$path])) {
                $methods[] = $method;
                if ($method === 'GET') { $methods[] = 'HEAD'; }
            }
        }

        // dynamische Kandidaten
        foreach ($this->routes as $method => $list) {
            foreach ($list as $route) {
                if (preg_match($route['regex'], $path)) {
                    $methods[] = $method;
                    if ($method === 'GET') { $methods[] = 'HEAD'; }
                }
            }
        }

        $methods[] = 'OPTIONS';
        $methods = array_values(array_unique($methods));

        return implode(', ', $methods);
    }

    /** Middleware-Stack + Handler ausführen */
    private function runRoute(array $route, Request $request, array $params): Response
    {
        // Stack innen: Handler
        $handler = $route['callback'];

        // Drum herum: Route-MW (inkl. global + Gruppen) in richtiger Reihenfolge
        $stack = array_reverse($route['middleware']);

        $next = function(Request $req, array $par) use ($handler): Response {
            // Handler akzeptiert (Request, array $params)
            $result = $handler($req, $par);
            
            // Auto-wrap result in Response if needed
            if ($result instanceof Response) {
                return $result;
            } elseif (is_string($result)) {
                return Response::html($result);
            } elseif (is_array($result) || is_object($result)) {
                return Response::json($result);
            } else {
                return Response::html((string)$result);
            }
        };

        foreach ($stack as $mw) {
            $prevNext = $next;
            $next = function(Request $req, array $par) use ($mw, $prevNext): Response {
                return $mw($req, $par, $prevNext);
            };
        }

        return $next($request, $params);
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }

    private function methodNotAllowed(string $allow): Response
    {
        return Response::text('Method Not Allowed', 405)->withHeader('Allow', $allow);
    }
}
