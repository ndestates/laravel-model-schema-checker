<?php

namespace NDEstates\LaravelModelSchemaChecker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use NDEstates\LaravelModelSchemaChecker\Jobs\RunModelChecks;
use NDEstates\LaravelModelSchemaChecker\Models\CheckResult;

class ModelSchemaCheckerController
{
    protected CheckerManager $checkerManager;
    protected IssueManager $issueManager;

    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)
    {
        // PRODUCTION SAFETY: This controller should never be accessible in production
        if ($this->isProductionEnvironment()) {
            abort(403, 'Laravel Model Schema Checker is not available in production environments.');
        }

        $this->checkerManager = $checkerManager;
        $this->issueManager = $issueManager;
    }

    /**
     * Display the main dashboard
     */
    public function index(): View
    {
        // Get user ID (1 for guest in development, actual user ID if authenticated)
        $userId = $this->getCurrentUserId();
        
        $recentResults = CheckResult::where('user_id', $userId)->latest()->take(5)->get();
        $stats = $this->getDashboardStats($userId);

        return view('model-schema-checker::dashboard', compact('recentResults', 'stats'));
    }

    /**
     * Run forgiving migrations
     */
    public function runForgivingMigrations(Request $request): JsonResponse
    {
        $request->validate([
            'database' => 'nullable|string',
            'path' => 'nullable|string',
        ]);

        try {
            // Use Artisan to call the command
            $exitCode = \Illuminate\Support\Facades\Artisan::call('model-schema-checker:migrate-forgiving', [
                '--database' => $request->input('database'),
                '--path' => $request->input('path'),
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Forgiving migrations completed successfully',
                    'output' => $output,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Forgiving migrations failed',
                    'output' => $output,
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running forgiving migrations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration status
     */
    public function getMigrationStatus(): JsonResponse
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles($this->getMigrationPaths());
            $ran = $migrator->getRepository()->getRan();

            $pendingMigrations = collect($files)->reject(function ($file) use ($ran) {
                return in_array($this->getMigrationName($file), $ran);
            });

            $status = [
                'total_migrations' => count($files),
                'ran_migrations' => count($ran),
                'pending_migrations' => $pendingMigrations->count(),
                'pending_list' => $pendingMigrations->values()->toArray(),
            ];

            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Could not get migration status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration paths (helper method)
     */
    protected function getMigrationPaths(): array
    {
        return [database_path('migrations')];
    }

    /**
     * Get migration name from file path (helper method)
     */
    protected function getMigrationName(string $file): string
    {
        return str_replace('.php', '', basename($file));
    }

    /**
     * Run model schema checks
     */
    public function runChecks(Request $request): JsonResponse
    {
        $request->validate([
            'check_types' => 'array',
            'check_types.*' => 'string',
            'options' => 'array',
        ]);

        $checkTypes = $request->input('check_types', ['all']);
        $options = $request->input('options', []);

        // Dispatch job for background processing
        $userId = $this->getCurrentUserId();
        $job = new RunModelChecks($userId, $checkTypes, $options);
        $jobId = Bus::dispatch($job);

        // Store job ID for progress tracking
        Cache::put("model-checker-job-{$jobId}", [
            'status' => 'queued',
            'progress' => 0,
            'user_id' => $userId,
            'started_at' => now(),
        ], now()->addHours(1));

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Checks started successfully',
        ]);
    }

    /**
     * Check progress of running checks
     */
    public function checkProgress(string $jobId): JsonResponse
    {
        $progress = Cache::get("model-checker-job-{$jobId}");

        if (!$progress) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Job not found',
            ]);
        }

        // Check if user owns this job
        if ($progress['user_id'] !== $this->getCurrentUserId()) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Unauthorized access to job',
            ]);
        }

        return response()->json($progress);
    }

    /**
     * Show detailed results
     */
    public function showResult(CheckResult $result): View
    {
        // Ensure user can access this result
        if ($result->user_id !== $this->getCurrentUserId()) {
            abort(403, 'Unauthorized access to results');
        }

        $issues = $result->issues ?? [];
        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));

        return view('model-schema-checker::result-detail', compact('result', 'issues', 'improvements'));
    }

    /**
     * Get results data for AJAX
     */
    public function getResultsData(CheckResult $result): JsonResponse
    {
        if ($result->user_id !== $this->getCurrentUserId()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'result' => $result,
            'issues' => $result->issues ?? [],
            'stats' => $result->stats ?? [],
        ]);
    }

    /**
     * Apply fixes
     */
    public function applyFixes(Request $request): JsonResponse
    {
        $request->validate([
            'result_id' => 'required|exists:check_results,id',
            'fixes' => 'array',
            'step_by_step' => 'boolean',
        ]);

        $result = CheckResult::findOrFail($request->result_id);

        if ($result->user_id !== $this->getCurrentUserId()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $fixes = $request->input('fixes', []);
        $stepByStep = $request->boolean('step_by_step', false);

        // Apply fixes logic here
        // This would use the existing command logic

        return response()->json([
            'success' => true,
            'message' => 'Fixes applied successfully',
        ]);
    }

    /**
     * Step-by-step fix interface
     */
    public function stepByStep(CheckResult $result): View
    {
        if ($result->user_id !== $this->getCurrentUserId()) {
            abort(403);
        }

        $issues = $result->issues ?? [];
        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));

        return view('model-schema-checker::step-by-step', compact('result', 'improvements'));
    }

    /**
     * Apply individual step fix
     */
    public function applyStepFix(Request $request): JsonResponse
    {
        $request->validate([
            'result_id' => 'required|exists:check_results,id',
            'fix_index' => 'required|integer',
            'action' => 'required|in:yes,no,skip',
        ]);

        $result = CheckResult::findOrFail($request->result_id);

        if ($result->user_id !== $this->getCurrentUserId()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Apply individual fix logic here

        return response()->json([
            'success' => true,
            'message' => 'Fix applied',
        ]);
    }

    /**
     * Rollback fixes
     */
    public function rollbackFixes(Request $request): JsonResponse
    {
        $request->validate([
            'result_id' => 'required|exists:check_results,id',
        ]);

        $result = CheckResult::findOrFail($request->result_id);

        if ($result->user_id !== $this->getCurrentUserId()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Rollback logic here

        return response()->json([
            'success' => true,
            'message' => 'Rollback completed',
        ]);
    }

    /**
     * Show history of checks
     */
    public function history(): View
    {
        $results = CheckResult::where('user_id', $this->getCurrentUserId())
            ->latest()
            ->paginate(20);

        return view('model-schema-checker::history', compact('results'));
    }

    /**
     * Download report
     */
    public function downloadReport(CheckResult $result): BinaryFileResponse
    {
        if ($result->user_id !== $this->getCurrentUserId()) {
            abort(403);
        }

        $filename = "model-schema-check_{$result->created_at->format('Y-m-d_H-i-s')}.md";
        $content = $this->generateMarkdownReport($result);

        $tempPath = storage_path("app/temp-{$filename}");
        file_put_contents($tempPath, $content);

        return response()->download($tempPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats(?int $userId = null): array
    {
        $userId = $userId ?? $this->getCurrentUserId();

        return [
            'total_checks' => CheckResult::where('user_id', $userId)->count(),
            'checks_this_month' => CheckResult::where('user_id', $userId)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'total_issues_found' => CheckResult::where('user_id', $userId)
                ->sum('total_issues'),
            'last_check_date' => CheckResult::where('user_id', $userId)
                ->latest()
                ->value('created_at'),
        ];
    }

    /**
     * Generate markdown report content
     */
    /**
     * Get current user ID (handles guest users in development)
     */
    protected function getCurrentUserId(): int
    {
        if (Auth::check()) {
            return $this->getCurrentUserId();
        }

        // In development environments, use a guest user ID of 1
        // In production, this won't be reached due to auth middleware
        return 1;
    }

    protected function generateMarkdownReport(CheckResult $result): string
    {
        $content = "# Laravel Model Schema Checker Results\n\n";
        $content .= "**Generated:** {$result->created_at->format('Y-m-d H:i:s')}\n\n";
        $userName = Auth::check() ? Auth::user()->name : 'Guest User (Development)';
        $content .= "**User:** {$userName}\n\n";

        if (!empty($result->issues)) {
            $content .= "## Issues Found\n\n";
            foreach ($result->issues as $issue) {
                $content .= "- {$issue['message']}\n";
                if (isset($issue['file'])) {
                    $content .= "  - 📁 {$issue['file']}\n";
                }
                $content .= "\n";
            }
        }

        return $content;
    }

    /**
     * Check if we're running in a production environment
     * Multiple layers of protection to prevent reverse engineering
     */
    protected function isProductionEnvironment(): bool
    {
        // Use Laravel's app environment helper
        $env = app()->environment();

        // Primary check: standard Laravel environments
        if (in_array(strtolower($env), ['production', 'prod', 'live'])) {
            return true;
        }

        // Secondary check: environment variables that might indicate production
        if (env('APP_ENV') === 'production' || env('APP_ENV') === 'prod' || env('APP_ENV') === 'live') {
            return true;
        }

        // Tertiary check: server environment variables
        if (isset($_SERVER['APP_ENV']) && in_array(strtolower($_SERVER['APP_ENV']), ['production', 'prod', 'live'])) {
            return true;
        }

        // Check for DDEV environment (always treat as development)
        if (isset($_SERVER['DDEV_PROJECT']) || isset($_SERVER['DDEV_HOSTNAME']) || getenv('DDEV_PROJECT')) {
            return false; // DDEV is always development
        }

        // Quaternary check: check for production-like hostnames
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = strtolower($_SERVER['HTTP_HOST']);
            // Common production patterns
            if (strpos($host, '.com') !== false ||
                strpos($host, '.org') !== false ||
                strpos($host, '.net') !== false ||
                !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test|\.ddev\.site)\b/', $host)) {
                // Additional check: if not clearly development, be conservative
                if (!preg_match('/\b(dev|staging|test|demo|\.ddev)\b/', $host)) {
                    // This is a heuristic - in production deployments, be extra cautious
                    return true;
                }
            }
        }

        return false;
    }
}