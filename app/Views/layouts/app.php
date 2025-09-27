<!DOCTYPE html>
<html lang="{{ $app_env ?? 'en' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Brick Framework Application')">
    <title>{{ $title ?? 'Brick Framework' }} - {{ $app_name ?? 'Brick App' }}</title>

    <!-- Tailwind CSS for quick styling -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Styles -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>

    @stack('styles')
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800">
                        <a href="/">{{ $app_name ?? 'Brick Framework' }}</a>
                    </h1>
                </div>

                <div class="flex space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">Home</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">Dashboard</a>
                    <a href="/about" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">About</a>
                    <a href="/contact" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">Contact</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; {{ date('Y') }} Brick Framework. Built with ❤️ by JP Behrens.</p>
            @if($app_env === 'development')
            <p class="text-gray-400 text-sm mt-2">Environment: {{ $app_env }} | PHP {{ PHP_VERSION }}</p>
            @endif
        </div>
    </footer>

    @stack('scripts')
</body>

</html>