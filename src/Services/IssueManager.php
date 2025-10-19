<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

class IssueManager
{
    protected array $issues = [];
    protected array $stats = [
        'total_issues' => 0,
        'issues_by_category' => [],
        'issues_by_type' => [],
        'issues_by_severity' => [],
    ];

    /**
     * Add an issue to the collection
     */
    public function addIssue(string $category, string $type, array $data): void
    {
        $issue = array_merge($data, [
            'category' => $category,
            'type' => $type,
            'timestamp' => now(),
        ]);

        $this->issues[] = $issue;

        // Update stats
        $this->stats['total_issues']++;
        $this->stats['issues_by_category'][$category] = ($this->stats['issues_by_category'][$category] ?? 0) + 1;
        $this->stats['issues_by_type'][$type] = ($this->stats['issues_by_type'][$type] ?? 0) + 1;

        if (isset($data['severity'])) {
            $this->stats['issues_by_severity'][$data['severity']] = ($this->stats['issues_by_severity'][$data['severity']] ?? 0) + 1;
        }
    }

    /**
     * Add multiple issues to the collection
     */
    public function addIssues(array $issues): void
    {
        foreach ($issues as $issue) {
            if (isset($issue['category']) && isset($issue['type'])) {
                $this->addIssue($issue['category'], $issue['type'], $issue);
            }
        }
    }

    /**
     * Get all issues
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Get issues by category
     */
    public function getIssuesByCategory(string $category): array
    {
        return array_filter($this->issues, fn($issue) => $issue['category'] === $category);
    }

    /**
     * Get issues by type
     */
    public function getIssuesByType(string $type): array
    {
        return array_filter($this->issues, fn($issue) => $issue['type'] === $type);
    }

    /**
     * Get issues by severity
     */
    public function getIssuesBySeverity(string $severity): array
    {
        return array_filter($this->issues, fn($issue) => ($issue['severity'] ?? 'medium') === $severity);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Attach an improvement to the last added issue
     */
    public function attachImprovementToLastIssue($improvement): void
    {
        if (!empty($this->issues)) {
            $this->issues[count($this->issues) - 1]['improvement'] = $improvement;
        }
    }

    /**
     * Check if there are any issues
     */
    public function hasIssues(): bool
    {
        return !empty($this->issues);
    }

    /**
     * Get issues count
     */
    public function count(): int
    {
        return count($this->issues);
    }
}