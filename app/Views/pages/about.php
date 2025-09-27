@extends('layouts.app')

@section('content')
<div class="bg-white rounded-lg shadow-lg p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">About Brick Framework</h1>
    
    <div class="prose max-w-none">
        <p class="text-lg text-gray-600 mb-6">
            Brick Framework is a modern, lightweight PHP framework designed for rapid development 
            without sacrificing performance or security.
        </p>
        
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Core Features</h2>
                <ul class="space-y-2 text-gray-600">
                    <li class="flex items-start">
                        <span class="text-green-500 mr-2">✓</span>
                        MVC Architecture with clean separation
                    </li>
                    <li class="flex items-start">
                        <span class="text-green-500 mr-2">✓</span>
                        Powerful Query Builder with multiple DB support
                    </li>
                    <li class="flex items-start">
                        <span class="text-green-500 mr-2">✓</span>
                        Blade-inspired template engine
                    </li>
                    <li class="flex items-start">
                        <span class="text-green-500 mr-2">✓</span>
                        Built-in security features
                    </li>
                    <li class="flex items-start">
                        <span class="text-green-500 mr-2">✓</span>
                        RESTful routing with middleware support
                    </li>
                </ul>
            </div>
            
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Performance</h2>
                <ul class="space-y-2 text-gray-600">
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-2">⚡</span>
                        Smart template compilation and caching
                    </li>
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-2">⚡</span>
                        Connection pooling and query optimization
                    </li>
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-2">⚡</span>
                        Memory-efficient request handling
                    </li>
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-2">⚡</span>
                        HTTP/2 ready with compression support
                    </li>
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-2">⚡</span>
                        Minimal overhead and fast bootstrap
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Technical Specifications</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">Requirements</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>PHP 8.1+</li>
                        <li>PDO Extension</li>
                        <li>OpenSSL Extension</li>
                        <li>Mbstring Extension</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">Databases</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>MySQL 5.7+</li>
                        <li>PostgreSQL 10+</li>
                        <li>SQLite 3.8+</li>
                        <li>MariaDB 10.2+</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">Web Servers</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>Apache 2.4+</li>
                        <li>Nginx 1.15+</li>
                        <li>PHP Built-in Server</li>
                        <li>IIS 10+</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Get Started</h2>
            <p class="text-gray-600 mb-6">
                Ready to build something amazing? Check out our quick start guide or explore the API.
            </p>
            <div class="space-x-4">
                <a href="/dashboard" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    View Dashboard
                </a>
                <a href="/api/info" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors">
                    API Documentation
                </a>
            </div>
        </div>
    </div>
</div>
@endsection