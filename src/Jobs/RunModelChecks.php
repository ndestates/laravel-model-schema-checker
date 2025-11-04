<?php

namespace NDEstates\LaravelModelSchemaChecker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use NDEstates\LaravelModelSchemaChecker\Models\CheckResult;

class RunModelChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public array $checkTypes;
    public array $options;
    public string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, array $checkTypes = ['all'], array $options = [])
    {
        $this->userId = $userId;
        $this->checkTypes = $checkTypes;
        $this->options = $options;
        $this->jobId = uniqid('model-check-', true);
    }

    /**
     * Execute the job.
     */
    public function handle(CheckerManager $checkerManager, IssueManager $issueManager): void
    {
        // Update progress
        $this->updateProgress('running', 10, 'Initializing checks...');

        try {
            // Create check result record
            $checkResult = CheckResult::create([
                'user_id' => $this->userId,
                'job_id' => $this->jobId,
                'status' => 'running',
                'check_types' => $this->checkTypes,
                'options' => $this->options,
                'started_at' => now(),
            ]);

            $this->updateProgress('running', 20, 'Setting up checker manager...');

            // Configure checker manager based on options
            $this->configureCheckerManager($checkerManager);

            $this->updateProgress('running', 30, 'Running checks...');

            // Run the checks
            $issues = $checkerManager->runAllChecks();

            $this->updateProgress('running', 80, 'Processing results...');

            // Get statistics
            $stats = $issueManager->getStats();

            // Update check result
            $checkResult->update([
                'status' => 'completed',
                'issues' => $issues,
                'stats' => $stats,
                'total_issues' => $stats['total_issues'] ?? 0,
                'critical_issues' => $this->countIssuesBySeverity($issues, 'critical'),
                'warning_issues' => $this->countIssuesBySeverity($issues, 'high'),
                'summary' => $this->generateSummary($stats),
                'completed_at' => now(),
            ]);

            $this->updateProgress('completed', 100, 'Checks completed successfully');

        } catch (\Exception $e) {
            Log::error('Model Schema Checker job failed', [
                'job_id' => $this->jobId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update check result with failure
            CheckResult::where('job_id', $this->jobId)->update([
                'status' => 'failed',
                'summary' => 'Check failed: ' . $e->getMessage(),
                'completed_at' => now(),
            ]);

            $this->updateProgress('failed', 0, 'Checks failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Configure the checker manager based on options
     */
    protected function configureCheckerManager(CheckerManager $checkerManager): void
    {
        // Configure based on check types
        if (!in_array('all', $this->checkTypes)) {
            // Enable only specific checkers
            $availableCheckers = $checkerManager->getAvailableCheckers();
            foreach ($availableCheckers as $checkerName => $checker) {
                if (!in_array($checkerName, $this->checkTypes)) {
                    $checkerManager->disableChecker($checkerName);
                }
            }
        }

        // Apply other options
        if (isset($this->options['dry_run'])) {
            // Configure dry run mode
        }
    }

    /**
     * Count issues by severity
     */
    protected function countIssuesBySeverity(array $issues, string $severity): int
    {
        return count(array_filter($issues, function ($issue) use ($severity) {
            return ($issue['severity'] ?? 'medium') === $severity;
        }));
    }

    /**
     * Generate a summary of the check results
     */
    protected function generateSummary(array $stats): string
    {
        $totalIssues = $stats['total_issues'] ?? 0;

        if ($totalIssues === 0) {
            return 'No issues found - your Laravel application looks good!';
        }

        return "Found {$totalIssues} issue(s) that may need attention.";
    }

    /**
     * Update job progress
     */
    protected function updateProgress(string $status, int $progress, string $message): void
    {
        Cache::put("model-checker-job-{$this->jobId}", [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'user_id' => $this->userId,
            'updated_at' => now(),
        ], now()->addHours(1));
    }
}