<?php

/**
 * Route Helper Functions
 * Vereinfacht Controller-Instanziierung und Route-Handling
 */

if (!function_exists('controller')) {
    /**
     * Controller-Instanz mit Standard-Response erstellen
     */
    function controller(string $controllerClass, \Core\Request $request): object
    {
        return new $controllerClass($request, \Core\Response::make());
    }
}

if (!function_exists('homeController')) {
    /**
     * HomeController-Shortcut
     */
    function homeController(\Core\Request $request): \App\Controllers\HomeController
    {
        return new \App\Controllers\HomeController($request, \Core\Response::make());
    }
}