<?php

namespace NDEstates\LaravelModelSchemaChecker\Contracts;

use Illuminate\Console\Command;

interface CheckerInterface
{
    /**
     * Execute the checker
     */
    public function check(): array;

    /**
     * Get the name of this checker
     */
    public function getName(): string;

    /**
     * Get the description of this checker
     */
    public function getDescription(): string;

    /**
     * Check if this checker is enabled
     */
    public function isEnabled(): bool;

    /**
     * Set the command instance for output
     */
    public function setCommand(Command $command): self;
}
