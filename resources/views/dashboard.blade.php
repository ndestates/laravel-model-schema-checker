<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Laravel Model Schema Checker - Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/model-schema-checker/css/app.css') }}">
    
    <!-- Scripts -->
    <script src="{{ asset('vendor/model-schema-checker/js/app.js') }}" defer></script>

    <!-- Styles -->
    <style>
        .progress-bar {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 20px;
            background-color: #3498db;
            width: 0%;
            transition: width 0.3s ease;
        }
        .issue-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .issue-critical { border-left: 4px solid #e74c3c; }
        .issue-high { border-left: 4px solid #f39c12; }
        .issue-medium { border-left: 4px solid #f1c40f; }
        .issue-low { border-left: 4px solid #27ae60; }
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
                            <h1 class="text-xl font-bold text-gray-900">Laravel Model Schema Checker</h1>
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
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Checks</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_checks'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">This Month</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['checks_this_month'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Issues</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_issues_found'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Last Check</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $stats['last_check_date'] ? $stats['last_check_date']->diffForHumans() : 'Never' }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check Form -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Run Model Schema Checks</h3>

                    <form id="checkForm" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Check Types</label>
                            <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="all" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">All Checks</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="models" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Models</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="relationships" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Relationships</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="security" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Security</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="performance" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Performance</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="migrations" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Migrations</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="check_types[]" value="code-quality" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2">Code Quality</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="submit" id="runChecksBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white hidden" id="loadingSpinner" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Run Checks
                            </button>
                        </div>
                    </form>

                    <!-- Progress Bar -->
                    <div id="progressContainer" class="mt-4 hidden">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700" id="progressText">Initializing...</span>
                            <span class="text-sm text-gray-500" id="progressPercent">0%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Check Results</h3>

                    @if($recentResults->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentResults as $result)
                                <div class="border rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                Check #{{ $result->id }}
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                {{ $result->created_at->format('M j, Y g:i A') }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            @if($result->status === 'completed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Completed
                                                </span>
                                            @elseif($result->status === 'running')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    Running
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Failed
                                                </span>
                                            @endif

                                            @if($result->total_issues > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    {{ $result->total_issues }} issues
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    No issues
                                                </span>
                                            @endif

                                            <a href="{{ route('model-schema-checker.results.show', $result) }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">No checks have been run yet. Start by running your first check above!</p>
                    @endif
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('checkForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const runBtn = document.getElementById('runChecksBtn');
            const spinner = document.getElementById('loadingSpinner');
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const progressPercent = document.getElementById('progressPercent');

            // Show loading state
            runBtn.disabled = true;
            spinner.classList.remove('hidden');
            runBtn.textContent = 'Starting Checks...';
            progressContainer.classList.remove('hidden');

            fetch('{{ route("model-schema-checker.run-checks") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    check_types: Array.from(formData.getAll('check_types[]')),
                    options: {}
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Start polling for progress
                    pollProgress(data.job_id);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    resetForm();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while starting the checks');
                resetForm();
            });
        });

        function pollProgress(jobId) {
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const progressPercent = document.getElementById('progressPercent');

            const poll = () => {
                fetch(`{{ url('/model-schema-checker') }}/check-progress/${jobId}`)
                    .then(response => response.json())
                    .then(data => {
                        progressFill.style.width = data.progress + '%';
                        progressText.textContent = data.message || 'Processing...';
                        progressPercent.textContent = data.progress + '%';

                        if (data.status === 'completed') {
                            progressText.textContent = 'Checks completed! Reloading page...';
                            setTimeout(() => window.location.reload(), 2000);
                        } else if (data.status === 'failed') {
                            progressText.textContent = 'Checks failed: ' + (data.message || 'Unknown error');
                            resetForm();
                        } else {
                            setTimeout(poll, 1000); // Poll again in 1 second
                        }
                    })
                    .catch(error => {
                        console.error('Error polling progress:', error);
                        progressText.textContent = 'Error checking progress';
                        resetForm();
                    });
            };

            poll();
        }

        function resetForm() {
            const runBtn = document.getElementById('runChecksBtn');
            const spinner = document.getElementById('loadingSpinner');

            runBtn.disabled = false;
            spinner.classList.add('hidden');
            runBtn.textContent = 'Run Checks';
        }
    </script>
</body>
</html>