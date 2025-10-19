<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Console\Command;
use NDEstates\LaravelModelSchemaChecker\Checkers\ModelChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\FilamentChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\SecurityChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\RelationshipChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\MigrationChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\ValidationChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\PerformanceChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\CodeQualityChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\LaravelFormsChecker;
use NDEstates\LaravelModelSchemaChecker\Contracts\CheckerInterface;

class CheckerManager
{
    protected array $checkers = [];
    protected Command $command;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registerDefaultCheckers();
    }

    public function setCommand(Command $command): self
    {
        $this->command = $command;

        // Set command on all checkers
        foreach ($this->checkers as $checker) {
            $checker->setCommand($command);
        }

        return $this;
    }

    protected function registerDefaultCheckers(): void
    {
        $this->register(new ModelChecker($this->config));
        $this->register(new FilamentChecker($this->config));
        $this->register(new SecurityChecker($this->config));
        $this->register(new RelationshipChecker($this->config));
        $this->register(new MigrationChecker($this->config));
        $this->register(new ValidationChecker($this->config));
        $this->register(new PerformanceChecker($this->config));
        $this->register(new CodeQualityChecker($this->config));
        $this->register(new LaravelFormsChecker($this->config));
        // TODO: Register other checkers as they are created
        // etc.
    }

    public function register(CheckerInterface $checker): self
    {
        $this->checkers[] = $checker;
        return $this;
    }

    public function getCheckers(): array
    {
        return $this->checkers;
    }

    public function getEnabledCheckers(): array
    {
        return array_filter($this->checkers, fn($checker) => $checker->isEnabled());
    }

    public function enableChecker(string $name): self
    {
        foreach ($this->checkers as $checker) {
            if ($checker->getName() === $name) {
                $checker->enable();
                break;
            }
        }
        return $this;
    }

    public function disableChecker(string $name): self
    {
        foreach ($this->checkers as $checker) {
            if ($checker->getName() === $name) {
                $checker->disable();
                break;
            }
        }
        return $this;
    }

    public function runAllChecks(): array
    {
        $allIssues = [];

        foreach ($this->getEnabledCheckers() as $checker) {
            $issues = $checker->check();
            $allIssues = array_merge($allIssues, $issues);
        }

        return $allIssues;
    }

    public function getChecker(string $name): ?CheckerInterface
    {
        foreach ($this->checkers as $checker) {
            if (str_contains(strtolower($checker->getName()), strtolower($name))) {
                return $checker;
            }
        }
        return null;
    }

    public function runCheck(string $checkerName): array
    {
        foreach ($this->checkers as $checker) {
            if ($checker->getName() === $checkerName && $checker->isEnabled()) {
                return $checker->check();
            }
        }

        return [];
    }

    public function getAvailableCheckers(): array
    {
        return array_map(function($checker) {
            return [
                'name' => $checker->getName(),
                'description' => $checker->getDescription(),
                'enabled' => $checker->isEnabled(),
            ];
        }, $this->checkers);
    }
}