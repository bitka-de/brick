@extends('layouts.app')

@section('content')
<div class="bg-white rounded-lg shadow-lg p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ $title ?? 'View System Test' }}</h1>
    
    <div class="grid md:grid-cols-2 gap-8">
        <!-- Template Features Test -->
        <div>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Template Features</h2>
            
            <div class="space-y-4">
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Variables & Escaping</h3>
                    <p><strong>Title:</strong> {{ $title ?? 'Default Title' }}</p>
                    <p><strong>User Input:</strong> {{ $user['name'] ?? 'Anonymous' }}</p>
                </div>
                
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Conditionals</h3>
                    @if($items ?? false)
                        <p class="text-green-600">✓ Items available</p>
                    @else
                        <p class="text-red-600">✗ No items found</p>
                    @endif
                    
                    @if($user ?? false)
                        <p class="text-blue-600">User: {{ $user['email'] ?? 'No email' }}</p>
                    @endif
                </div>
                
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Loops</h3>
                    @if($items ?? false)
                        <ul class="list-disc list-inside">
                            @foreach($items as $item)
                                <li class="text-gray-600">{{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-500">No items to display</p>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">System Information</h2>
            
            <div class="space-y-4">
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Environment</h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Framework:</strong> Brick Framework v1.0</p>
                        <p><strong>Environment:</strong> {{ $app_env ?? 'unknown' }}</p>
                        <p><strong>PHP Version:</strong> {{ PHP_VERSION }}</p>
                        <p><strong>Server:</strong> {{ $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' }}</p>
                    </div>
                </div>
                
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Memory Usage</h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Current:</strong> {{ round(memory_get_usage() / 1024 / 1024, 2) }} MB</p>
                        <p><strong>Peak:</strong> {{ round(memory_get_peak_usage() / 1024 / 1024, 2) }} MB</p>
                        <p><strong>Limit:</strong> {{ ini_get('memory_limit') }}</p>
                    </div>
                </div>
                
                <div class="border border-gray-200 rounded p-4">
                    <h3 class="font-semibold text-gray-700 mb-2">Template Cache</h3>
                    <div class="text-sm text-gray-600">
                        @php
                            $cacheDir = __DIR__ . '/../../../storage/cache/views';
                            $cacheExists = is_dir($cacheDir);
                        @endphp
                        
                        @if($cacheExists)
                            <p class="text-green-600">✓ Cache directory exists</p>
                            <p><strong>Path:</strong> <code class="text-xs">storage/cache/views</code></p>
                        @else
                            <p class="text-red-600">✗ Cache directory not found</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Test Navigation -->
    <div class="mt-8 text-center border-t border-gray-200 pt-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Test Navigation</h2>
        <div class="space-x-4">
            <a href="/test/database" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                Database Test
            </a>
            <a href="/test/cache" class="inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
                Cache Test
            </a>
            <a href="/api/info" class="inline-block bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition-colors">
                API Info
            </a>
            <a href="/" class="inline-block bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition-colors">
                Back to Home
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
console.log('🧱 View System Test loaded successfully!');
var templateItems = @json($items ?? []);
console.log('Template variables:', templateItems);
</script>
@endpush
@endsection