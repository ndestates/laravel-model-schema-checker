<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Console\Command;
use NDEstates\LaravelModelSchemaChecker\Checkers\ModelChecker;
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
        // TODO: Register other checkers as they are created
        // $this->register(new SecurityChecker($this->config));
        // $this->register(new RelationshipChecker($this->config));
        // $this->register(new PerformanceChecker($this->config));
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