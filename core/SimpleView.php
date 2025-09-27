<?php

declare(strict_types=1);

namespace Core;

/**
 * Simple View System - Memory-optimiert
 * Ersetzt das komplexe Template-System für stabilen Betrieb
 */
class SimpleView
{
    private static array $data = [];
    private static string $viewPath = '';
    
    public static function init(string $viewPath): void
    {
        self::$viewPath = rtrim($viewPath, '/');
    }
    
    public static function make(string $view, array $data = []): string
    {
        // Reset data for each view to prevent memory accumulation
        self::$data = $data;
        
        // Convert dot notation to file path
        $viewFile = str_replace('.', '/', $view) . '.php';
        $fullPath = self::$viewPath . '/' . $viewFile;
        
        if (!file_exists($fullPath)) {
            // Fallback: try in app/Views
            $appPath = __DIR__ . '/../app/Views/' . $viewFile;
            if (file_exists($appPath)) {
                $fullPath = $appPath;
            } else {
                throw new \RuntimeException("View not found: {$view} (searched: {$fullPath}, {$appPath})");
            }
        }
        
        return self::renderPhp($fullPath, self::$data);
    }
    
    private static function renderPhp(string $file, array $data): string
    {
        // Extract variables for template
        extract($data, EXTR_SKIP);
        
        // Start output buffering
        ob_start();
        
        try {
            include $file;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException("Error rendering view: " . $e->getMessage(), 0, $e);
        }
    }
    
    public static function render(string $view, array $data = []): string
    {
        return self::make($view, $data);
    }
}

/**
 * View Facade für Kompatibilität
 */
class View
{
    public static function make(string $view, array $data = []): string
    {
        // Initialize if not done
        if (empty(SimpleView::class)) {
            SimpleView::init(__DIR__ . '/../app/Views');
        }
        
        return SimpleView::make($view, $data);
    }
    
    public static function render(string $view, array $data = []): string
    {
        return self::make($view, $data);
    }
}