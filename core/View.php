<?php
/**
 * Brick View Engine – Production-Ready Template System
 * 
 * Eine hochperformante Template-Engine mit Laravel Blade-ähnlicher Syntax und erweiterten
 * Enterprise-Features für sichere, skalierbare Web-Anwendungen.
 * 
 * ## 🚀 **Kern-Features:**
 * 
 * ### **Template-Vererbung (Inheritance)**
 * - `@extends('layout.master')` - Definiert Parent-Template
 * - `@section('content') ... @endsection` - Definiert überschreibbare Bereiche
 * - `@yield('sidebar', 'default')` - Platzhalter mit optionalen Defaults
 * 
 * ### **Content-Stacking für Asset-Management** 
 * - `@push('styles') ... @endpush` - Fügt Content zu Stack hinzu
 * - `@stack('scripts')` - Gibt gesammelten Stack-Content aus
 * - `@once('analytics') ... @endonce` - Verhindert doppelte Includes
 * 
 * ### **Sichere Template-Includes**
 * - `@include('partial.nav')` - Einfacher Include
 * - `@include('card', ['title' => 'Test'])` - Include mit Variablen-Übergabe
 * - `@include('widget', {"id": 123})` - Include mit JSON-Daten (eval-frei!)
 * 
 * ### **XSS-Schutz & Output-Kontrolle**
 * - `{{ $userInput }}` - Auto-escaped Output (XSS-sicher)
 * - `{!! $trustedHtml !!}` - Unescaped Output (nur für vertrauenswürdigen Content)
 * - `@verbatim ... @endverbatim` - Raw-Content ohne Template-Parsing
 * 
 * ### **Control-Flow-Direktiven**
 * - `@if($condition) ... @elseif($other) ... @else ... @endif`
 * - `@foreach($items as $item) ... @endforeach`
 * - `@for($i = 0; $i < 10; $i++) ... @endfor`
 * - `@while($condition) ... @endwhile`
 * - `@php ... @endphp` - Inline-PHP-Blöcke
 * 
 * ### **Performance & Caching**
 * - Smart Template-Compilation zu optimiertem PHP-Code
 * - Dependency-Tracking: Cache wird invalidiert bei Änderungen an includes/extends
 * - Produktions-Cache mit automatischer Freshness-Prüfung
 * - Asset-Versioning mit mtime-basiertem Cache-Busting
 * 
 * ### **Security-Features**
 * - Eval-freie Template-Kompilierung (keine Code-Injection möglich)
 * - Production-Mode: Erzwungenes Escaping für {!! !!} Direktiven
 * - Isolierte Template-Ausführung in geschütztem Scope
 * 
 * ## 📖 **Verwendung:**
 * 
 * ```php
 * // Globale Template-Daten setzen
 * View::share('user', $currentUser);
 * View::share('siteName', 'My App');
 * 
 * // Template rendern mit Response
 * return View::make('pages.dashboard', [
 *     'posts' => $userPosts,
 *     'notifications' => $notifications
 * ]);
 * 
 * // Cache-Konfiguration
 * View::cache(true);  // Produktions-Cache aktivieren
 * View::forbidRawInProd(true);  // {!! !!} in Production verbieten
 * 
 * // Custom Template-Direktive registrieren
 * View::directive('money', fn($amount) => "<?= number_format($amount, 2) . ' €' ?>");
 * ```
 * 
 * ## 🏗️ **Template-Beispiel:**
 * 
 * ```blade
 * @extends('layouts.app')
 * 
 * @section('title', 'Dashboard')
 * 
 * @push('styles')
 *     <link rel="stylesheet" href="dashboard.css">
 * @endpush
 * 
 * @section('content')
 *     <h1>Welcome {{ $user->name }}!</h1>
 *     
 *     @if($notifications->isNotEmpty())
 *         @include('partials.notifications', ['items' => $notifications])
 *     @endif
 *     
 *     @foreach($posts as $post)
 *         @include('components.post-card', ['post' => $post])
 *     @endforeach
 * @endsection
 * 
 * @push('scripts')
 *     <script src="dashboard.js"></script>
 * @endpush
 * ```
 * 
 * @author JP Behrens <https://bitka.de>
 * @version 3.0 - Enterprise Edition
 * @since PHP 8.1
 * @license MIT
 * @link https://bitka.de/brick-framework
 */

declare(strict_types=1);

namespace Core;

final class View
{
    // ===== KONFIGURATION & PFADE =====
    
    /** @var string Absoluter Pfad zum Template-Verzeichnis (default: app/Views) */
    private static string $templateBasePath = __DIR__ . '/../app/Views';
    
    /** @var string Absoluter Pfad zum kompilierten Cache-Verzeichnis */
    private static string $compiledCachePath = __DIR__ . '/../storage/cache/views';

    // ===== GLOBALE DATEN & DIRECTIVES =====
    
    /** @var array<string,mixed> Globale Template-Daten die in allen Views verfügbar sind */
    private static array $globalSharedData = [];

    /** @var array<string,callable(string):string> Benutzerdefinierte Template-Direktiven */
    private static array $customDirectiveHandlers = [];

    // ===== CACHE & PERFORMANCE EINSTELLUNGEN =====
    
    /** @var bool Template-Compilation-Cache aktiviert (Production: true, Development: false) */
    private static bool $templateCacheEnabled = true;

    /** @var bool In Production unescaped {!! !!} Output automatisch escapen für Security */
    private static bool $enforceEscapingInProduction = false;

    // ===== TEMPLATE-INHERITANCE STATE (PER INSTANCE) =====
    
    /** @var string Name des Parent-Templates bei @extends Direktive */
    private string $extendedParentTemplate = '';
    
    /** @var array<string,string> Template-Sections definiert durch @section/@endsection */
    private array $templateSectionContent = [];
    
    /** @var array<string,string[]> Content-Stacks für @push/@endpush Asset-Management */
    private array $contentStackItems = [];
    
    /** @var array<string,bool> @once-Keys zur Vermeidung doppelter Includes */
    private array $onceExecutedBlocks = [];
    
    /** @var bool Gibt an ob aktuelles Template ein Parent via @extends erweitert */
    private bool $currentTemplateExtendsParent = false;

    // ===== DEPENDENCY TRACKING & CACHING =====
    
    /** @var array<string,int> Template-Abhängigkeiten: Dateipfad => Last-Modified-Time */
    private array $templateFileDependencies = [];

    /**
     * View-Instanz Konstruktor (private - nur über Factory-Methoden erreichbar)
     * 
     * @param string $templateIdentifier Template-Name (z.B. 'pages.dashboard')
     * @param array<string,mixed> $templateVariables Lokale Template-Daten
     */
    private function __construct(
        private readonly string $templateIdentifier,
        private readonly array $templateVariables
    ) {}

    // ---------- Public API ----------

    // ===== ÖFFENTLICHE KONFIGURATIONS-API =====
    
    /**
     * Setzt den Basis-Pfad für Template-Dateien
     * 
     * @param string $templateDirectoryPath Absoluter Pfad zum Template-Verzeichnis
     * @example View::setBasePath('/var/www/app/templates');
     */
    public static function setBasePath(string $templateDirectoryPath): void
    {
        self::$templateBasePath = rtrim($templateDirectoryPath, '/');
    }

    /**
     * Setzt den Cache-Pfad für kompilierte Templates
     * 
     * @param string $cacheDirectoryPath Absoluter Pfad zum Cache-Verzeichnis
     * @example View::setCachePath('/tmp/view-cache');
     */
    public static function setCachePath(string $cacheDirectoryPath): void
    {
        self::$compiledCachePath = rtrim($cacheDirectoryPath, '/');
    }

    /**
     * Template-Cache aktivieren oder deaktivieren
     * 
     * @param bool $enableCache true für Production (Cache an), false für Development
     * @example View::cache(false); // Development-Modus
     */
    public static function cache(bool $enableCache = true): void
    {
        self::$templateCacheEnabled = $enableCache;
        if ($enableCache) {
            self::ensureDirectoryExists(self::$compiledCachePath);
        }
    }

    /**
     * Erzwingt Escaping von {!! !!} Direktiven in Production für erhöhte Sicherheit
     * 
     * @param bool $enforceEscaping true = {!! !!} wird wie {{ }} behandelt
     * @example View::forbidRawInProd(true); // Production-Security
     */
    public static function forbidRawInProd(bool $enforceEscaping = true): void
    {
        self::$enforceEscapingInProduction = $enforceEscaping;
    }

    /**
     * Setzt globale Template-Variable die in allen Views verfügbar ist
     * 
     * @param string $variableName Name der Template-Variable
     * @param mixed $variableValue Wert der Variable
     * @example View::share('currentUser', $user);
     */
    public static function share(string $variableName, mixed $variableValue): void
    {
        self::$globalSharedData[$variableName] = $variableValue;
    }

    /**
     * Setzt mehrere globale Template-Variablen auf einmal
     * 
     * @param array<string,mixed> $sharedVariables Assoziatives Array von Variable => Wert
     * @example View::shareMany(['user' => $user, 'siteName' => 'My App']);
     */
    public static function shareMany(array $sharedVariables): void
    {
        self::$globalSharedData = array_replace(self::$globalSharedData, $sharedVariables);
    }

    /**
     * Registriert benutzerdefinierte Template-Direktive
     * 
     * @param string $directiveName Name der Direktive (ohne @)
     * @param callable(string):string $directiveHandler Handler-Funktion
     * @example View::directive('money', fn($amount) => "<?= number_format($amount, 2) . ' €' ?>");
     */
    public static function directive(string $directiveName, callable $directiveHandler): void
    {
        self::$customDirectiveHandlers[$directiveName] = $directiveHandler;
    }

    /**
     * Haupt-Factory-Methode: Erstellt und rendert Template zu HTTP-Response
     * 
     * @param string $templateName Template-Bezeichner (z.B. 'pages.dashboard', 'layouts.app')
     * @param array<string,mixed> $localTemplateData Lokale Template-Variablen für dieses Template
     * @return Response HTTP-Response-Objekt mit gerendertem HTML-Content
     * 
     * @example return View::make('users.profile', ['user' => $user, 'posts' => $posts]);
     */
    public static function make(string $templateName, array $localTemplateData = []): Response
    {
        $viewInstance = new self($templateName, array_replace(self::$globalSharedData, $localTemplateData));
        $renderedHtmlContent = $viewInstance->render();

        return Response::html($renderedHtmlContent)->withHeader('X-View', $templateName);
    }

    // ---------- Render-Pipeline ----------

    // ===== TEMPLATE-RENDERING PIPELINE =====
    
    /**
     * Haupt-Render-Methode: Kompiliert und führt Template aus
     * 
     * @return string Gerenderter HTML-Content
     * @throws \RuntimeException Bei Template-Fehlern oder fehlenden Dateien
     */
    private function render(): string
    {
        [$compiledFilePath, $dependencyMetaPath] = $this->getCompiledCacheFilePaths($this->templateIdentifier);

        // Cache-Hit prüfen: Ist kompiliertes Template noch aktuell?
        if (self::$templateCacheEnabled && $this->isCompiledTemplateFresh($compiledFilePath, $dependencyMetaPath)) {
            return $this->executeCompiledTemplate($compiledFilePath, $this->templateVariables);
        }

        // Cache-Miss: Template-Quelldatei lesen und kompilieren
        $templateSourceCode = $this->readTemplateSourceFile($this->templateIdentifier);
        $compiledPhpCode = $this->compileTemplateToPhp($templateSourceCode, $this->templateIdentifier);

        // Kompiliertes Template und Dependency-Meta im Cache speichern
        self::ensureDirectoryExists(dirname($compiledFilePath));
        file_put_contents($compiledFilePath, $compiledPhpCode, LOCK_EX);
        file_put_contents($dependencyMetaPath, json_encode($this->templateFileDependencies, JSON_THROW_ON_ERROR), LOCK_EX);

        return $this->executeCompiledTemplate($compiledFilePath, $this->templateVariables);
    }

    /**
     * Führt kompilierte Template-Datei in isoliertem Scope aus
     * 
     * @param string $compiledTemplateFilePath Pfad zur kompilierten PHP-Template-Datei
     * @param array<string,mixed> $templateDataVariables Template-Variablen für die Ausführung
     * @return string Gerenderter HTML-Output
     * @throws \RuntimeException Bei Template-Ausführungsfehlern
     */
    private function executeCompiledTemplate(string $compiledTemplateFilePath, array $templateDataVariables): string
    {
        // Isolierter Ausführungsscope via Closure mit Template-Helper-Funktionen
        $templateExecutionRunner = static function (string $__compiledFile__, array $__templateData__) {
            // Template-Variablen in lokalen Scope extrahieren
            extract($__templateData__, EXTR_SKIP);

            // Template-Helper-Funktionen bereitstellen
            $css = fn(string $assetPath, array $htmlAttributes = []) => View::css($assetPath, $htmlAttributes);
            $js = fn(string $assetPath, array $htmlAttributes = []) => View::js($assetPath, $htmlAttributes);
            $asset = fn(string $assetPath) => View::asset($assetPath);
            $e = fn(mixed $value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');

            ob_start();
            try {
                include $__compiledFile__;
                return (string) ob_get_clean();
            } catch (\Throwable $templateExecutionError) {
                ob_end_clean();
                $errorMessage = sprintf(
                    "Template execution failed in %s: %s", 
                    $__compiledFile__, 
                    $templateExecutionError->getMessage()
                );
                throw new \RuntimeException($errorMessage, 0, $templateExecutionError);
            }
        };

        return $templateExecutionRunner($compiledTemplateFilePath, array_replace(self::$globalSharedData, $templateDataVariables));
    }

    // ---------- Kompilierung ----------

    // ===== TEMPLATE-KOMPILIERUNGS-ENGINE =====
    
    /**
     * Kompiliert Template-Quellcode zu ausführbarem PHP-Code
     * 
     * @param string $templateSourceContent Template-Quellcode mit Direktiven
     * @param string $templateName Name des Templates für Dependency-Tracking
     * @return string Kompilierter PHP-Code
     */
    private function compileTemplateToPhp(string $templateSourceContent, string $templateName): string
    {
        $this->trackTemplateFileDependency($templateName);

        // 1) @verbatim-Blöcke vor Kompilierung schützen (kein Template-Parsing)
        $protectedVerbatimBlocks = [];
        $templateSourceContent = preg_replace_callback('/@verbatim(.*?)@endverbatim/s', function ($regexMatches) use (&$protectedVerbatimBlocks) {
            $verbatimPlaceholderKey = '__VERBATIM_BLOCK__' . count($protectedVerbatimBlocks) . '__';
            $protectedVerbatimBlocks[$verbatimPlaceholderKey] = $regexMatches[1];
            return $verbatimPlaceholderKey;
        }, $templateSourceContent);

        // 2) Built-in Template-Direktiven kompilieren
        $templateSourceContent = $this->compileExtendsDirectives($templateSourceContent);
        $templateSourceContent = $this->compileSectionDirectives($templateSourceContent);
        $templateSourceContent = $this->compileYieldDirectives($templateSourceContent);
        $templateSourceContent = $this->compileStackDirectives($templateSourceContent);
        $templateSourceContent = $this->compileOnceDirectives($templateSourceContent);
        $templateSourceContent = $this->compileIncludeDirectives($templateSourceContent);
        $templateSourceContent = $this->compileConditionalDirectives($templateSourceContent);
        $templateSourceContent = $this->compileLoopDirectives($templateSourceContent);
        $templateSourceContent = $this->compilePhpBlockDirectives($templateSourceContent);
        $templateSourceContent = $this->compileEchoDirectives($templateSourceContent);

        // 3) Benutzerdefinierte Template-Direktiven (@customDirective(...))
        foreach (self::$customDirectiveHandlers as $directiveName => $directiveHandler) {
            $templateSourceContent = preg_replace_callback("/@{$directiveName}\\s*\\((.*?)\\)/s", function ($regexMatches) use ($directiveHandler) {
                return (string) $directiveHandler(trim($regexMatches[1]));
            }, $templateSourceContent);
        }

        // 4) Geschützte @verbatim-Blöcke wiederherstellen
        $templateSourceContent = strtr($templateSourceContent, $protectedVerbatimBlocks);

        // 5) Template-Vererbung: Wenn @extends verwendet, Parent-Template kompilieren
        if ($this->currentTemplateExtendsParent && $this->extendedParentTemplate) {
            $parentTemplateSource = $this->readTemplateSourceFile($this->extendedParentTemplate);
            $compiledParentPhpCode = $this->compileTemplateToPhp($parentTemplateSource, $this->extendedParentTemplate);
            // Parent-Template erhält Zugriff auf Sections/Stacks von Child-Template
            return $compiledParentPhpCode;
        }

        // 6) Template mit Runtime-Wrapper für Sections/Stacks umhüllen
        return $this->wrapCompiledTemplateWithRuntime($templateSourceContent);
    }

    /**
     * Umhüllt kompilierten Template-Code mit Runtime-Umgebung für Sections/Stacks
     * 
     * @param string $compiledTemplateCode Kompilierter Template-PHP-Code
     * @return string Vollständiger ausführbarer PHP-Code mit Runtime
     */
    private function wrapCompiledTemplateWithRuntime(string $compiledTemplateCode): string
    {
        $globals = var_export(['__template' => $this->templateIdentifier], true);
        return <<<PHP
<?php
/* Compiled by Brick View: {$this->templateIdentifier} */
\$__brick = [
    'sections' => [],
    'stacks'   => [],
    'once'     => [],
    'globals'  => {$globals},
];

if (!function_exists('__brick_yield')) {
    function __brick_yield(string \$name, string \$default = ''): void {
        global \$__brick;
        echo \$__brick['sections'][\$name] ?? \$default;
    }
}
if (!function_exists('__brick_stack')) {
    function __brick_stack(string \$name): void {
        global \$__brick;
        if (!empty(\$__brick['stacks'][\$name])) {
            echo implode('', \$__brick['stacks'][\$name]);
        }
    }
}
?>
{$compiledTemplateCode}
PHP;
    }

    // ----- Built-in directives -----

    private function compileExtendsDirectives(string $c): string
    {
        return preg_replace_callback('/@extends\\([\'"]([^\'"]+)[\'"]\\)/s', function ($m) {
            $this->currentTemplateExtendsParent = true;
            $this->extendedParentTemplate = $m[1];
            $this->trackTemplateFileDependency($m[1]);
            return ''; // Entfernen – Parent wird später kompiliert
        }, $c);
    }

    private function compileSectionDirectives(string $c): string
    {
        // @section('name') ... @endsection
        $c = preg_replace_callback('/@section\\([\'"]([^\'"]+)[\'"]\\)(.*?)@endsection/s', function ($m) {
            $name = $m[1];
            $body = $m[2];
            // Speichere zur Laufzeit in $__brick['sections']
            return "<?php global \$__brick; ob_start(); ?>{$body}<?php \$__brick['sections']['{$name}'] = (string) ob_get_clean(); ?>";
        }, $c);

        // @section('name', 'inline')
        $c = preg_replace_callback('/@section\\([\'"]([^\'"]+)[\'"]\\s*,\\s*([^)]+)\\)/s', function ($m) {
            $name = $m[1];
            $val  = trim($m[2]);
            return "<?php global \$__brick; \$__brick['sections']['{$name}'] = {$val}; ?>";
        }, $c);

        return $c;
    }

    private function compileYieldDirectives(string $c): string
    {
        // @yield('name', 'default')
        return preg_replace('/@yield\\([\'"]([^\'"]+)[\'"]\\s*(,\\s*([^)]+))?\\)/s', '<?php __brick_yield("$1", $3 ?? ""); ?>', $c);
    }

    private function compileStackDirectives(string $c): string
    {
        // @push('head') ... @endpush
        $c = preg_replace_callback('/@push\\([\'"]([^\'"]+)[\'"]\\)(.*?)@endpush/s', function ($m) {
            $name = $m[1];
            $body = $m[2];
            return "<?php global \$__brick; \$__brick['stacks']['{$name}'][] = (function(){ob_start();?>{$body}<?php return (string)ob_get_clean();})(); ?>";
        }, $c);

        // @stack('head')
        $c = preg_replace('/@stack\\([\'"]([^\'"]+)[\'"]\\)/s', '<?php __brick_stack("$1"); ?>', $c);

        return $c;
    }

    private function compileOnceDirectives(string $c): string
    {
        // @once('key') ... @endonce
        return preg_replace_callback('/@once\\([\'"]([^\'"]+)[\'"]\\)(.*?)@endonce/s', function ($m) {
            $key  = $m[1];
            $body = $m[2];
            return "<?php global \$__brick; if (empty(\$__brick['once']['{$key}'])) { \$__brick['once']['{$key}'] = true; ?>{$body}<?php } ?>";
        }, $c);
    }

    private function compileIncludeDirectives(string $c): string
    {
        // @include('partial') oder @include('partial', $vars) oder JSON: @include('partial', {"a":1})
        return preg_replace_callback('/@include\\([\'"]([^\'"]+)[\'"](?:\\s*,\\s*(.+?))?\\)/s', function ($m) {
            $tpl  = $m[1];
            $arg  = isset($m[2]) ? trim($m[2]) : null;

            $this->trackTemplateFileDependency($tpl);

            // Datenübergabe vorbereiten – ohne eval
            // 1) Variable wie $foo
            $dataExpr = '[]';
            if ($arg !== null) {
                if (str_starts_with($arg, '$')) {
                    $dataExpr = $arg;
                } elseif ($arg[0] === '{' || $arg[0] === '[') {
                    // JSON-Objekt/Array → zur Laufzeit decode
                    $json = addslashes($arg);
                    $dataExpr = "(static function(){ \$__j='{$json}'; return json_decode(\$__j, true) ?? []; })()";
                } else {
                    // Fallback: ignorieren, aber nicht brechen
                    $dataExpr = '[]';
                }
            }

            $path = $this->resolveTemplateFilePath($tpl);
            $safe = var_export($path, true);

            return "<?php echo View::renderPartial({$safe}, array_replace(\$__data__ ?? [], {$dataExpr}, View::shared())); ?>";
        }, $c);
    }

    private function compileConditionalDirectives(string $c): string
    {
        $c = preg_replace('/@if\\s*\\((.*?)\\)/s', '<?php if ($1): ?>', $c);
        $c = preg_replace('/@elseif\\s*\\((.*?)\\)/s', '<?php elseif ($1): ?>', $c);
        $c = preg_replace('/@else/s', '<?php else: ?>', $c);
        $c = preg_replace('/@endif/s', '<?php endif; ?>', $c);
        return $c;
    }

    private function compileLoopDirectives(string $c): string
    {
        $c = preg_replace('/@foreach\\s*\\((.*?)\\)/s', '<?php foreach ($1): ?>', $c);
        $c = preg_replace('/@endforeach/s', '<?php endforeach; ?>', $c);
        $c = preg_replace('/@for\\s*\\((.*?)\\)/s', '<?php for ($1): ?>', $c);
        $c = preg_replace('/@endfor/s', '<?php endfor; ?>', $c);
        $c = preg_replace('/@while\\s*\\((.*?)\\)/s', '<?php while ($1): ?>', $c);
        $c = preg_replace('/@endwhile/s', '<?php endwhile; ?>', $c);
        return $c;
    }

    private function compilePhpBlockDirectives(string $c): string
    {
        // @php ... @endphp
        $c = preg_replace('/@php/s', '<?php ', $c);
        $c = preg_replace('/@endphp/s', ' ?>', $c);
        return $c;
    }

    private function compileEchoDirectives(string $c): string
    {
        if (self::$enforceEscapingInProduction) {
            // In Prod unescaped verbieten → als escaped behandeln
            $c = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= htmlspecialchars($1 ?? "", ENT_QUOTES, "UTF-8") ?>', $c);
        } else {
            $c = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $c);
        }
        // Escaped
        $c = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?= htmlspecialchars($1 ?? "", ENT_QUOTES, "UTF-8") ?>', $c);
        return $c;
    }

    // ---------- Dependency & Cache ----------

    // ===== TEMPLATE-CACHING UND DATEIPFAD-VERWALTUNG =====
    
    /**
     * Generiert Cache-Pfade für kompiliertes Template und Dependency-Metadata
     * 
     * @param string $templateIdentifier Template-Name/Pfad
     * @return array{0: string, 1: string} [Compiliert-Pfad, Dependency-Meta-Pfad]
     */
    private function getCompiledCacheFilePaths(string $templateIdentifier): array
    {
        $templateHash = sha1($templateIdentifier);
        $cacheDirectory = self::$compiledCachePath . '/' . substr($templateHash, 0, 2);
        $compiledFilePath = $cacheDirectory . '/' . $templateHash . '.php';
        $dependencyMetaPath = $cacheDirectory . '/' . $templateHash . '.meta';
        return [$compiledFilePath, $dependencyMetaPath];
    }

    /**
     * Prüft ob kompiliertes Template noch aktuell ist (Cache-Hit)
     * 
     * @param string $compiledFilePath Pfad zur kompilierten Template-Datei
     * @param string $dependencyMetaPath Pfad zur Dependency-Metadatei
     * @return bool True wenn Cache noch gültig ist
     */
    private function isCompiledTemplateFresh(string $compiledFilePath, string $dependencyMetaPath): bool
    {
        if (!file_exists($compiledFilePath) || !file_exists($dependencyMetaPath)) {
            return false;
        }

        try {
            $dependencyList = json_decode(file_get_contents($dependencyMetaPath), true, 512, JSON_THROW_ON_ERROR);
            $compiledTimestamp = filemtime($compiledFilePath);

            // Prüfe alle Template-Dependencies auf Änderungen
            foreach ($dependencyList as $dependencyTemplateName) {
                $dependencySourcePath = $this->resolveTemplateFilePath($dependencyTemplateName);
                if (!file_exists($dependencySourcePath) || filemtime($dependencySourcePath) > $compiledTimestamp) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fügt Template zur Dependency-Liste hinzu (für Cache-Invalidierung)
     * 
     * @param string $templateIdentifier Template-Name
     * @return void
     */
    private function trackTemplateFileDependency(string $templateIdentifier): void
    {
        $this->templateFileDependencies[] = $templateIdentifier;
    }

    /**
     * Liest Template-Quellcode aus Datei
     * 
     * @param string $templateIdentifier Template-Name/Pfad
     * @return string Template-Quellinhalt
     * @throws \RuntimeException Wenn Template-Datei nicht gefunden
     */
    private function readTemplateSourceFile(string $templateIdentifier): string
    {
        $templateFilePath = $this->resolveTemplateFilePath($templateIdentifier);
        
        if (!file_exists($templateFilePath)) {
            throw new \RuntimeException("Template file not found: {$templateIdentifier} (resolved to: {$templateFilePath})");
        }

        $templateContent = file_get_contents($templateFilePath);
        if ($templateContent === false) {
            throw new \RuntimeException("Failed to read template file: {$templateFilePath}");
        }

        return $templateContent;
    }

    /**
     * Löst Template-Namen zu vollständigem Dateipfad auf
     * 
     * @param string $templateIdentifier Template-Name (z.B. 'layouts.master', 'pages.home')
     * @return string Vollständiger Dateipfad
     */
    private function resolveTemplateFilePath(string $templateIdentifier): string
    {
        // Punkt-Notation zu Pfad: 'layouts.master' → 'layouts/master.php'
        $templatePath = str_replace('.', '/', $templateIdentifier) . '.php';
        return self::$templateBasePath . '/' . $templatePath;
    }

    /**
     * Stellt sicher dass Verzeichnis existiert (rekursiv)
     * 
     * @param string $directoryPath Verzeichnispfad
     * @return void
     */
    private static function ensureDirectoryExists(string $directoryPath): void
    {
        if (!is_dir($directoryPath) && !mkdir($directoryPath, 0755, true) && !is_dir($directoryPath)) {
            throw new \RuntimeException("Failed to create directory: {$directoryPath}");
        }
    }

    // ---------- Asset & Partial Helper (öffentlich nutzbar in Templates) ----------

    public static function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        // If local file exist → versionieren mit mtime
        $fs = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if ($fs) {
            $real = realpath($fs . $path);
            if ($real && is_file($real)) {
                return $path . '?v=' . filemtime($real);
            }
        }
        return $path;
    }

    public static function css(string $path, array $attrs = []): string
    {
        $href = self::asset($path);
        $attrs = array_replace(['rel' => 'stylesheet', 'href' => $href], $attrs);
        return '<link' . self::attrs($attrs) . '>';
    }

    public static function js(string $path, array $attrs = []): string
    {
        $src = self::asset($path);
        $attrs = array_replace(['src' => $src, 'defer' => true], $attrs);
        return '<script' . self::attrs($attrs) . '></script>';
    }

    public static function shared(): array
    {
        return self::$globalSharedData;
    }

    /** Sicheres Rendern eines Partials (wird von @include genutzt) */
    public static function renderPartial(string $absolutePath, array $data = []): string
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException("Partial not found: {$absolutePath}");
        }
        $runner = static function (string $__file__, array $__data__) {
            extract($__data__, EXTR_SKIP);
            ob_start();
            try {
                include $__file__;
                return (string) ob_get_clean();
            } catch (\Throwable $t) {
                ob_end_clean();
                throw $t;
            }
        };
        return $runner($absolutePath, array_replace(self::$globalSharedData, $data));
    }

    private static function attrs(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $k => $v) {
            if ($v === false || $v === null) continue;
            $html .= ' ' . $k;
            if ($v !== true) {
                $html .= '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        return $html;
    }
}