@extends('layouts.app')

@section('content')
<div class="gradient-bg text-white rounded-lg p-8 mb-8">
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-4">{{ $title ?? 'Welcome to Brick Framework' }}</h1>
        <p class="text-xl opacity-90">{{ $message ?? 'A modern PHP framework for rapid development' }}</p>
        <p class="mt-4 opacity-75">Version {{ $version ?? '1.0.0' }} running in {{ $environment ?? 'development' }} mode</p>
    </div>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Quick Start -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center mb-4">
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-semibold ml-3">Quick Start</h3>
        </div>
        <p class="text-gray-600 mb-4">Get started with Brick Framework in minutes</p>
        <a href="/dashboard" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
            View Dashboard
        </a>
    </div>

    <!-- API Testing -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center mb-4">
            <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-semibold ml-3">API Ready</h3>
        </div>
        <p class="text-gray-600 mb-4">RESTful APIs with JSON responses</p>
        <a href="/api/health" class="inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
            Test API
        </a>
    </div>

    <!-- Documentation -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center mb-4">
            <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
            <h3 class="text-xl font-semibold ml-3">Documentation</h3>
        </div>
        <p class="text-gray-600 mb-4">Learn about framework features</p>
        <a href="/about" class="inline-block bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition-colors">
            Learn More
        </a>
    </div>
</div>

@if($environment === 'development')
<div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
    <div class="flex items-center mb-2">
        <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
        </svg>
        <h3 class="text-lg font-semibold text-yellow-800">Development Mode</h3>
    </div>
    <p class="text-yellow-700 mb-3">You're running in development mode. Here are some useful endpoints:</p>
    <div class="space-y-1">
        <a href="/test/database" class="block text-blue-600 hover:text-blue-800">• Database Connection Test</a>
        <a href="/test/view" class="block text-blue-600 hover:text-blue-800">• View System Test</a>
        <a href="/test/cache" class="block text-blue-600 hover:text-blue-800">• Cache System Test</a>
        <a href="/api/info" class="block text-blue-600 hover:text-blue-800">• System Information API</a>
    </div>
</div>
@endif
@endsection