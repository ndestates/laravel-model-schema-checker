<?php

namespace NDEstates\LaravelModelSchemaChecker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckResult extends Model
{
    protected $fillable = [
        'user_id',
        'job_id',
        'status',
        'check_types',
        'options',
        'issues',
        'stats',
        'total_issues',
        'critical_issues',
        'warning_issues',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'check_types' => 'array',
        'options' => 'array',
        'issues' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the check result
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }

    /**
     * Get the applied fixes for this check result
     */
    public function appliedFixes(): HasMany
    {
        return $this->hasMany(AppliedFix::class);
    }

    /**
     * Check if the result is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the result has issues
     */
    public function hasIssues(): bool
    {
        return $this->total_issues > 0;
    }

    /**
     * Get issues by severity
     */
    public function getIssuesBySeverity(string $severity): array
    {
        return array_filter($this->issues ?? [], function ($issue) use ($severity) {
            return ($issue['severity'] ?? 'medium') === $severity;
        });
    }

    /**
     * Get issues by type
     */
    public function getIssuesByType(string $type): array
    {
        return array_filter($this->issues ?? [], function ($issue) use ($type) {
            return ($issue['type'] ?? '') === $type;
        });
    }

    /**
     * Get improvement suggestions
     */
    public function getImprovements(): array
    {
        return array_filter($this->issues ?? [], function ($issue) {
            return isset($issue['improvement']);
        });
    }

    /**
     * Get auto-fixable improvements
     */
    public function getAutoFixableImprovements(): array
    {
        return array_filter($this->getImprovements(), function ($issue) {
            $improvement = $issue['improvement'];
            return $improvement->canAutoFix();
        });
    }
}