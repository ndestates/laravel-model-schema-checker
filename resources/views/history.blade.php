<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Check History - Laravel Model Schema Checker</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    <style>
        .history-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        .history-item:hover {
            border-color: #3498db;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-completed { color: #27ae60; }
        .status-running { color: #3498db; }
        .status-failed { color: #e74c3c; }
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
                            <h1 class="text-2xl font-bold text-gray-900">Check History</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                View and manage past model schema checks
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('model-schema-checker.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Run New Check
                            </a>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="flex flex-wrap gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select id="statusFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">All Statuses</option>
                                    <option value="completed">Completed</option>
                                    <option value="running">Running</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                                <select id="dateFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Has Issues</label>
                                <select id="issuesFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">All</option>
                                    <option value="with_issues">With Issues</option>
                                    <option value="no_issues">No Issues</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History List -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    @if($results->count() > 0)
                        <div id="historyList">
                            @foreach($results as $result)
                                <div class="history-item" data-status="{{ $result->status }}" data-date="{{ $result->created_at->format('Y-m-d') }}" data-issues="{{ $result->total_issues > 0 ? 'with_issues' : 'no_issues' }}">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3">
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    Check #{{ $result->id }}
                                                </h3>

                                                @if($result->status === 'completed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 status-completed">
                                                        Completed
                                                    </span>
                                                @elseif($result->status === 'running')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 status-running">
                                                        Running
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 status-failed">
                                                        Failed
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $result->created_at->format('F j, Y \a\t g:i A') }}
                                                ({{ $result->created_at->diffForHumans() }})
                                            </p>

                                            <div class="mt-2 flex items-center space-x-6 text-sm">
                                                @if($result->status === 'completed')
                                                    @if($result->total_issues > 0)
                                                        <span class="text-red-600">
                                                            {{ $result->total_issues }} issues found
                                                            ({{ $result->critical_issues }} critical, {{ $result->high_issues }} high, {{ $result->medium_issues }} medium, {{ $result->low_issues }} low)
                                                        </span>
                                                    @else
                                                        <span class="text-green-600">No issues found</span>
                                                    @endif

                                                    @if($result->total_fixes_applied > 0)
                                                        <span class="text-blue-600">
                                                            {{ $result->total_fixes_applied }} fixes applied
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-500">
                                                        Check in progress...
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('model-schema-checker.results.show', $result) }}" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                View Details
                                            </a>

                                            @if($result->status === 'completed' && $result->total_issues > 0)
                                                <a href="{{ route('model-schema-checker.step-by-step', $result) }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    Fix Issues
                                                </a>
                                            @endif

                                            <button onclick="deleteResult({{ $result->id }})" class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($results->hasPages())
                            <div class="mt-6">
                                {{ $results->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No checks found</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by running your first model schema check.</p>
                            <div class="mt-6">
                                <a href="{{ route('model-schema-checker.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Run First Check
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>

    <script>
        // Filter functionality
        document.getElementById('statusFilter').addEventListener('change', filterResults);
        document.getElementById('dateFilter').addEventListener('change', filterResults);
        document.getElementById('issuesFilter').addEventListener('change', filterResults);

        function filterResults() {
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const issuesFilter = document.getElementById('issuesFilter').value;

            const items = document.querySelectorAll('.history-item');

            items.forEach(item => {
                let show = true;

                // Status filter
                if (statusFilter && item.dataset.status !== statusFilter) {
                    show = false;
                }

                // Issues filter
                if (issuesFilter && item.dataset.issues !== issuesFilter) {
                    show = false;
                }

                // Date filter
                if (dateFilter) {
                    const itemDate = new Date(item.dataset.date);
                    const today = new Date();
                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());

                    if (dateFilter === 'today' && itemDate.toDateString() !== today.toDateString()) {
                        show = false;
                    } else if (dateFilter === 'week' && itemDate < weekAgo) {
                        show = false;
                    } else if (dateFilter === 'month' && itemDate < monthAgo) {
                        show = false;
                    }
                }

                item.style.display = show ? 'block' : 'none';
            });
        }

        function deleteResult(resultId) {
            if (!confirm('Are you sure you want to delete this check result? This action cannot be undone.')) return;

            fetch(`{{ url('/model-schema-checker') }}/results/${resultId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item from the DOM
                    const item = document.querySelector(`[data-status][data-date][data-issues]`);
                    // Find the specific item by checking all items
                    const items = document.querySelectorAll('.history-item');
                    items.forEach(item => {
                        // This is a simplified approach - in a real app you'd want to match by ID
                        if (item.textContent.includes(`Check #${resultId}`)) {
                            item.remove();
                        }
                    });

                    // If no items left, show empty state
                    if (document.querySelectorAll('.history-item').length === 0) {
                        location.reload(); // Reload to show empty state properly
                    }
                } else {
                    alert('Error deleting result: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the result');
            });
        }
    </script>
</body>
</html>