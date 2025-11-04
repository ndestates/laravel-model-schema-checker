<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Check Results #{{ $result->id }} - Laravel Model Schema Checker</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/model-schema-checker/css/app.css') }}">
    
    <!-- Scripts -->
    <script src="{{ asset('vendor/model-schema-checker/js/app.js') }}" defer></script>

    <!-- Styles -->
    <style>
        .issue-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .issue-critical { border-left: 4px solid #e74c3c; background-color: #fdf2f2; }
        .issue-high { border-left: 4px solid #f39c12; background-color: #fef9e7; }
        .issue-medium { border-left: 4px solid #f1c40f; background-color: #fefce8; }
        .issue-low { border-left: 4px solid #27ae60; background-color: #f0f9ff; }
        .fix-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .fix-button:hover { background-color: #2980b9; }
        .fix-button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
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
                            <a href="{{ route('model-schema-checker.dashboard') }}" class="text-xl font-bold text-gray-900 hover:text-gray-700">
                                ← Laravel Model Schema Checker
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('model-schema-checker.history') }}" class="text-gray-700 hover:text-gray-900">History</a>
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
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Check Results #{{ $result->id }}</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Run on {{ $result->created_at->format('F j, Y \a\t g:i A') }}
                                ({{ $result->created_at->diffForHumans() }})
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            @if($result->status === 'completed')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    Completed
                                </span>
                            @elseif($result->status === 'running')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    Running
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    Failed
                                </span>
                            @endif

                            @if($result->total_issues > 0)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    {{ $result->total_issues }} issues found
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    No issues found
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-600">{{ $result->critical_issues }}</div>
                            <div class="text-sm text-gray-500">Critical</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">{{ $result->high_issues }}</div>
                            <div class="text-sm text-gray-500">High</div>
                        </div>
                        <div class="text class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ $result->medium_issues }}</div>
                            <div class="text-sm text-gray-500">Medium</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $result->low_issues }}</div>
                            <div class="text-sm text-gray-500">Low</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $result->total_fixes_applied }}</div>
                            <div class="text-sm text-gray-500">Fixes Applied</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Issues List -->
            @if($result->issues->count() > 0)
                <div class="bg-white shadow rounded-lg mb-8">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-medium text-gray-900">Issues Found</h2>
                            <div class="flex space-x-2">
                                <button id="applyAllFixesBtn" class="fix-button" {{ $result->issues->where('can_fix', true)->count() === 0 ? 'disabled' : '' }}>
                                    Apply All Fixes
                                </button>
                                <button id="stepByStepBtn" class="fix-button">
                                    Step-by-Step Fixes
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4" id="issuesList">
                            @foreach($result->issues->sortByDesc('severity') as $issue)
                                <div class="issue-card issue-{{ $issue->severity }}">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <h3 class="text-sm font-medium text-gray-900">{{ $issue->title }}</h3>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                    @if($issue->severity === 'critical') bg-red-100 text-red-800
                                                    @elseif($issue->severity === 'high') bg-orange-100 text-orange-800
                                                    @elseif($issue->severity === 'medium') bg-yellow-100 text-yellow-800
                                                    @else bg-blue-100 text-blue-800
                                                    @endif">
                                                    {{ ucfirst($issue->severity) }}
                                                </span>
                                                @if($issue->can_fix)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        Auto-fixable
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="text-sm text-gray-600 mb-2">{{ $issue->description }}</p>

                                            @if($issue->file_path)
                                                <p class="text-xs text-gray-500 mb-2">
                                                    <strong>File:</strong> {{ $issue->file_path }}
                                                    @if($issue->line_number)
                                                        :{{ $issue->line_number }}
                                                    @endif
                                                </p>
                                            @endif

                                            @if($issue->code_snippet)
                                                <details class="mb-2">
                                                    <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Show code snippet</summary>
                                                    <pre class="mt-1 text-xs bg-gray-100 p-2 rounded overflow-x-auto"><code>{{ $issue->code_snippet }}</code></pre>
                                                </details>
                                            @endif

                                            @if($issue->suggestion)
                                                <div class="mt-2 p-2 bg-blue-50 rounded text-sm">
                                                    <strong class="text-blue-800">Suggestion:</strong>
                                                    <p class="text-blue-700 mt-1">{{ $issue->suggestion }}</p>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="ml-4 flex flex-col space-y-2">
                                            @if($issue->can_fix)
                                                <button class="fix-button text-xs py-1 px-2" onclick="applyFix({{ $issue->id }})">
                                                    Apply Fix
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No issues found!</h3>
                        <p class="mt-1 text-sm text-gray-500">Your models are following best practices.</p>
                    </div>
                </div>
            @endif

            <!-- Applied Fixes -->
            @if($result->appliedFixes->count() > 0)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Applied Fixes</h2>

                        <div class="space-y-3">
                            @foreach($result->appliedFixes as $fix)
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-green-800">{{ $fix->issue_title }}</p>
                                        <p class="text-xs text-green-600">{{ $fix->applied_at->format('M j, Y g:i A') }}</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        @if($fix->can_rollback)
                                            <button class="text-xs text-red-600 hover:text-red-800 underline" onclick="rollbackFix({{ $fix->id }})">
                                                Rollback
                                            </button>
                                        @endif
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Applied
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </main>
    </div>

    <script>
        function applyFix(issueId) {
            if (!confirm('Are you sure you want to apply this fix?')) return;

            fetch(`{{ url('/model-schema-checker') }}/apply-fix/${issueId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Fix applied successfully!');
                    location.reload();
                } else {
                    alert('Error applying fix: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while applying the fix');
            });
        }

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
                    location.reload();
                } else {
                    alert('Error rolling back fix: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rolling back the fix');
            });
        }

        document.getElementById('applyAllFixesBtn')?.addEventListener('click', function() {
            if (!confirm('Are you sure you want to apply all auto-fixable issues?')) return;

            this.disabled = true;
            this.textContent = 'Applying...';

            fetch(`{{ url('/model-schema-checker') }}/apply-all-fixes/{{ $result->id }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('All fixes applied successfully!');
                    location.reload();
                } else {
                    alert('Error applying fixes: ' + (data.message || 'Unknown error'));
                    this.disabled = false;
                    this.textContent = 'Apply All Fixes';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while applying fixes');
                this.disabled = false;
                this.textContent = 'Apply All Fixes';
            });
        });

        document.getElementById('stepByStepBtn')?.addEventListener('click', function() {
            window.location.href = `{{ route('model-schema-checker.step-by-step', $result) }}`;
        });
    </script>
</body>
</html>