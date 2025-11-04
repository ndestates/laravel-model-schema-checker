<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Setup Required - Laravel Model Schema Checker</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="antialiased bg-gray-50">
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center mb-4">
                <div class="bg-yellow-100 rounded-full p-3 mr-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Setup Required</h1>
                    <p class="text-gray-600">Database tables need to be created before you can use the dashboard.</p>
                </div>
            </div>
        </div>

        <!-- Missing Tables -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Missing Database Tables</h2>
            <p class="text-gray-600 mb-4">The following tables are required but don't exist in your database:</p>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($missing_tables as $table)
                        <li class="text-red-700"><code class="bg-red-100 px-2 py-1 rounded">{{ $table }}</code></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Setup Instructions -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">How to Fix This</h2>

            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-900 mb-2">Option 1: Run Migrations</h3>
                    <p class="text-blue-800 mb-3">Execute the following command to create the required tables:</p>
                    <code class="block bg-blue-100 p-3 rounded text-sm font-mono">php artisan migrate</code>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-semibold text-green-900 mb-2">Option 2: Use the Web Interface</h3>
                    <p class="text-green-800 mb-3">If you have the migration tools enabled, you can run migrations directly from the web:</p>
                    <a href="{{ route('model-schema-checker.migrations.forgiving') }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Run Forgiving Migration
                    </a>
                </div>
            </div>
        </div>

        <!-- What These Tables Do -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">What These Tables Do</h2>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">check_results</h3>
                    <p class="text-gray-600 text-sm">Stores the results of schema checks, including issues found, statistics, and check metadata.</p>
                </div>

                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">applied_fixes</h3>
                    <p class="text-gray-600 text-sm">Tracks automatic fixes that have been applied to your code, with rollback capabilities.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>