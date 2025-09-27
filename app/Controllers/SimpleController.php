<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * SimpleController für schnelle Tests
 */
class SimpleController 
{
    public function home(): string
    {
        return $this->renderSimpleTemplate('layouts/simple', [
            'content' => $this->renderSimpleTemplate('pages/simple-home'),
            'title' => 'Home - Brick Framework'
        ]);
    }
    
    public function dashboard(): string
    {
        $content = '<div class="bg-white p-8 rounded-lg shadow">
            <h1 class="text-3xl font-bold mb-4">Dashboard</h1>
            <p>This is a simple dashboard page.</p>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="bg-blue-100 p-4 rounded">
                    <h3 class="font-semibold">Memory Usage</h3>
                    <p>' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB</p>
                </div>
                <div class="bg-green-100 p-4 rounded">
                    <h3 class="font-semibold">PHP Version</h3>
                    <p>' . PHP_VERSION . '</p>
                </div>
            </div>
        </div>';
        
        return $this->renderSimpleTemplate('layouts/simple', [
            'content' => $content,
            'title' => 'Dashboard - Brick Framework'
        ]);
    }
    
    private function renderSimpleTemplate(string $template, array $data = []): string
    {
        $templatePath = __DIR__ . "/../Views/{$template}.php";
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        
        // Extract variables
        extract($data);
        
        // Start output buffering
        ob_start();
        include $templatePath;
        
        return ob_get_clean();
    }
}