<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Brick Framework' ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?= $description ?? 'Modernes PHP Framework mit MVC-Architektur' ?>">
    <meta name="author" content="Brick Framework">
</head>
<body class="bg-gray-50 font-sans antialiased">
    <!-- Simple Navigation -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Brick Framework</h1>
            <div class="space-x-4">
                <a href="/" class="hover:text-blue-200">Home</a>
                <a href="/dashboard" class="hover:text-blue-200">Dashboard</a>
                <a href="/about" class="hover:text-blue-200">About</a>
                <a href="/contact" class="hover:text-blue-200">Contact</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="container mx-auto py-8 px-4">
        <?= $content ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-4 text-center">
        <p>&copy; <?= date('Y') ?> Brick Framework</p>
    </footer>
</body>
</html>