<?php

/**
 * Simple Routes Configuration für Brick Framework
 */

use App\Controllers\SimpleController;

return [
    'GET /' => 'SimpleController@home',
    'GET /dashboard' => 'SimpleController@dashboard',
    'GET /about' => function() {
        return '<!DOCTYPE html>
<html>
<head>
    <title>About - Brick Framework</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto">
            <h1 class="text-xl font-bold">Brick Framework</h1>
        </div>
    </nav>
    <main class="container mx-auto py-8 px-4">
        <div class="bg-white p-8 rounded-lg shadow">
            <h1 class="text-3xl font-bold mb-4">About Brick Framework</h1>
            <p class="mb-4">Brick Framework ist ein modernes PHP-Framework mit MVC-Architektur.</p>
            <p>Built with ❤️ by JP Behrens.</p>
            <div class="mt-6">
                <a href="/" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">← Back to Home</a>
            </div>
        </div>
    </main>
</body>
</html>';
    }
];