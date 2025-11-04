<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Step-by-Step Fixes - Laravel Model Schema Checker</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/model-schema-checker/css/app.css') }}">
    
    <!-- Scripts -->
    <script src="{{ asset('vendor/model-schema-checker/js/app.js') }}" defer></script>

    <!-- Styles -->
    <style>
        .step-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            background-color: #f9fafb;
        }
        .step-active { border-color: #3498db; background-color: #ebf8ff; }
        .step-completed { border-color: #27ae60; background-color: #f0f9ff; }
        .step-pending { opacity: 0.6; }
        .fix-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 8px;
        }
        .fix-button:hover { background-color: #2980b9; }
        .fix-button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
        .skip-button {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .skip-button:hover { background-color: #7f8c8d; }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="{{ route('model-schema-checker.results.show', $result) }}" class="text-xl font-bold text-gray-900 hover:text-gray-700">
                                ← Back to Results
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        @auth
                            <span class="text-gray-500">Welcome, {{ Auth::user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-gray-700 hover:text-gray-900">Logout</button>
                            </form>
                        @else
                            <span class="text-gray-500">Guest User (Development Mode)</span>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:p-6">
                    <h1 class="text-2xl font-bold text-gray-900">Step-by-Step Fixes</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Check #{{ $result->id }} - {{ $fixableIssues->count() }} fixable issues remaining
                    </p>

                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Progress</span>
                            <span class="text-sm text-gray-500" id="progressText">
                                {{ $completedFixes }} of {{ $totalFixableIssues }} fixes applied
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $totalFixableIssues > 0 ? ($completedFixes / $totalFixableIssues) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Step -->
            @if($currentIssue)
                <div class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium text-gray-900">Current Issue (Step {{ $currentStep }})</h2>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @if($currentIssue->severity === 'critical') bg-red-100 text-red-800
                                @elseif($currentIssue->severity === 'high') bg-orange-100 text-orange-800
                                @elseif($currentIssue->severity === 'medium') bg-yellow-100 text-yellow-800
                                @else bg-blue-100 text-blue-800
                                @endif">
                                {{ ucfirst($currentIssue->severity) }} Priority
                            </span>
                        </div>

                        <div class="step-card step-active">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">{{ $currentIssue->title }}</h3>
                            <p class="text-gray-600 mb-4">{{ $currentIssue->description }}</p>

                            @if($currentIssue->file_path)
                                <p class="text-sm text-gray-500 mb-3">
                                    <strong>File:</strong> {{ $currentIssue->file_path }}
                                    @if($currentIssue->line_number)
                                        :{{ $currentIssue->line_number }}
                                    @endif
                                </p>
                            @endif

                            @if($currentIssue->code_snippet)
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Current Code:</h4>
                                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><code>{{ $currentIssue->code_snippet }}</code></pre>
                                </div>
                            @endif

                            @if($currentIssue->suggestion)
                                <div class="mb-4 p-3 bg-blue-50 rounded">
                                    <h4 class="text-sm font-medium text-blue-800 mb-1">Suggested Fix:</h4>
                                    <p class="text-blue-700 text-sm">{{ $currentIssue->suggestion }}</p>
                                </div>
                            @endif

                            <div class="flex items-center space-x-3">
                                <button id="applyCurrentFixBtn" class="fix-button">
                                    Apply This Fix
                                </button>
                                <button id="skipCurrentFixBtn" class="skip-button">
                                    Skip This Issue
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Completed Steps -->
            @if($completedIssues->count() > 0)
                <div class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Completed Fixes</h2>

                        <div class="space-y-3">
                            @foreach($completedIssues as $issue)
                                <div class="step-card step-completed">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ $issue->title }}</h4>
                                            <p class="text-sm text-gray-600">{{ $issue->description }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Fixed at {{ $issue->appliedFix->applied_at->format('M j, Y g:i A') }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            @if($issue->appliedFix->can_rollback)
                                                <button class="text-xs text-red-600 hover:text-red-800 underline" onclick="rollbackFix({{ $issue->appliedFix->id }})">
                                                    Rollback
                                                </button>
                                            @endif
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Applied
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Remaining Steps Preview -->
            @if($remainingIssues->count() > 0)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Remaining Issues ({{ $remainingIssues->count() }})</h2>

                        <div class="space-y-3">
                            @foreach($remainingIssues->take(5) as $index => $issue)
                                <div class="step-card step-pending">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-900">Step {{ $currentStep + $index + 1 }}: {{ $issue->title }}</h4>
                                            <p class="text-sm text-gray-600">{{ Str::limit($issue->description, 100) }}</p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                            @if($issue->severity === 'critical') bg-red-100 text-red-800
                                            @elseif($issue->severity === 'high') bg-orange-100 text-orange-800
                                            @elseif($issue->severity === 'medium') bg-yellow-100 text-yellow-800
                                            @else bg-blue-100 text-blue-800
                                            @endif">
                                            {{ ucfirst($issue->severity) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach

                            @if($remainingIssues->count() > 5)
                                <p class="text-sm text-gray-500 text-center py-2">
                                    ... and {{ $remainingIssues->count() - 5 }} more issues
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Completion Message -->
            @if(!$currentIssue && $totalFixableIssues > 0)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900">All fixable issues have been addressed!</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            You've successfully applied {{ $completedFixes }} fixes.
                            <a href="{{ route('model-schema-checker.results.show', $result) }}" class="text-blue-600 hover:text-blue-800">
                                View detailed results →
                            </a>
                        </p>
                    </div>
                </div>
            @endif
        </main>
    </div>

    <script>
        @if($currentIssue)
        document.getElementById('applyCurrentFixBtn').addEventListener('click', function() {
            if (!confirm('Are you sure you want to apply this fix?')) return;

            this.disabled = true;
            this.textContent = 'Applying...';

            fetch(`{{ url('/model-schema-checker') }}/apply-fix/{{ $currentIssue->id }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Move to next step
                    window.location.reload();
                } else {
                    alert('Error applying fix: ' + (data.message || 'Unknown error'));
                    this.disabled = false;
                    this.textContent = 'Apply This Fix';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while applying the fix');
                this.disabled = false;
                this.textContent = 'Apply This Fix';
            });
        });

        document.getElementById('skipCurrentFixBtn').addEventListener('click', function() {
            if (!confirm('Are you sure you want to skip this issue? You can come back to it later.')) return;

            fetch(`{{ url('/model-schema-checker') }}/skip-fix/{{ $currentIssue->id }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error skipping fix: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while skipping the fix');
            });
        });
        @endif

        function rollbackFix(fixId) {
            if (!confirm('Are you sure you want to rollback this fix? This action cannot be undone.')) return;

            fetch(`{{ url('/model-schema-checker') }}/rollback-fix/${fixId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Fix rolled back successfully!');
                    window.location.reload();
                } else {
                    alert('Error rolling back fix: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rolling back the fix');
            });
        }
    </script>
</body>
</html>