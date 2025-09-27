<?php
/**
 * Global Helper Functions for Brick Framework
 */

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        
        if ($value === null) {
            return $default;
        }
        
        // String to boolean conversion
        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                'null', 'nil', '' => null,
                default => $value,
            };
        }
        
        return $value;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the app directory
     * 
     * @param string $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        return ROOT_PATH . '/app' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base directory
     * 
     * @param string $path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return ROOT_PATH . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the path to the config directory
     * 
     * @param string $path
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return ROOT_PATH . '/config' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage directory
     * 
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return ROOT_PATH . '/storage' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public directory
     * 
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return ROOT_PATH . '/public' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     * 
     * @param mixed ...$vars
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variables
     * 
     * @param mixed ...$vars
     */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}