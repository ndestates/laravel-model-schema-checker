<?php

namespace NDEstates\LaravelModelSchemaChecker\Contracts;

interface CodeImprovementInterface
{
    /**
     * Get the file path this improvement applies to
     */
    public function getFilePath(): string;

    /**
     * Get the line number this improvement applies to
     */
    public function getLineNumber(): ?int;

    /**
     * Get the type of improvement (e.g., 'security', 'performance', 'quality')
     */
    public function getType(): string;

    /**
     * Get the severity level (low, medium, high, critical)
     */
    public function getSeverity(): string;

    /**
     * Get the improvement title
     */
    public function getTitle(): string;

    /**
     * Get detailed description of the improvement
     */
    public function getDescription(): string;

    /**
     * Get the suggested code changes
     */
    public function getSuggestedChanges(): array;

    /**
     * Check if this improvement can be automatically applied
     */
    public function canAutoFix(): bool;

    /**
     * Apply the improvement automatically
     */
    public function applyFix(): bool;

    /**
     * Get the original code snippet
     */
    public function getOriginalCode(): string;

    /**
     * Get the improved code snippet
     */
    public function getImprovedCode(): string;
}