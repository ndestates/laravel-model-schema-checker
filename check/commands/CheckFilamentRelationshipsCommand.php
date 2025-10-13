<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class CheckFilamentRelationshipsCommand
{
    private Logger $logger;
    private CheckConfig $config;

    public function __construct(CheckConfig $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->logger->info("Starting Filament relationship check...");
        echo "Starting Filament relationship check...\n";

        $resourcesPath = app_path('Filament');
        $resourceFiles = File::allFiles($resourcesPath);
        $totalErrors = 0;

        foreach ($resourceFiles as $file) {
            $filePath = $file->getRealPath();
            if (!preg_match('/Resource\.php$/', $filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            $resourceClass = $this->getClassFullNameFromFile($filePath);

            if (!$resourceClass || !method_exists($resourceClass, 'getModel')) {
                continue;
            }

            $modelClass = $resourceClass::getModel();
            if (!class_exists($modelClass)) {
                $this->logAndEcho("Error: Model class '{$modelClass}' not found for resource '{$resourceClass}'.", 'error');
                $totalErrors++;
                continue;
            }

            // Skip abstract classes as they cannot be instantiated
            $reflection = new ReflectionClass($modelClass);
            if ($reflection->isAbstract()) {
                $this->logAndEcho("Warning: Skipping abstract model class '{$modelClass}' used in '{$resourceClass}'.", 'warning');
                continue;
            }

            preg_match_all('/->relationship\(\s*\'([a-zA-Z0-9_]+)\'/', $content, $matches);

            if (empty($matches[1])) {
                continue;
            }

            $model = new $modelClass;
            foreach ($matches[1] as $relationshipName) {
                if (!method_exists($model, $relationshipName)) {
                    $this->logAndEcho("Error: Relationship '{$relationshipName}' not found on model '{$modelClass}' used in '{$filePath}'.", 'error');
                    $totalErrors++;
                    continue;
                }

                $reflection = new ReflectionMethod($modelClass, $relationshipName);
                if (!$reflection->isPublic()) {
                    $this->logAndEcho("Error: Relationship method '{$relationshipName}' is not public on model '{$modelClass}' used in '{$filePath}'.", 'error');
                    $totalErrors++;
                }
            }
        }

        if ($totalErrors > 0) {
            $this->logAndEcho("Filament relationship check found {$totalErrors} errors.", 'error');
        } else {
            $this->logAndEcho("Filament relationship check completed successfully. No issues found.", 'info');
        }
    }

    private function logAndEcho(string $message, string $level = 'info'): void
    {
        $this->logger->{$level}($message);
        echo $message . "\n";
    }

    private function getClassFullNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        $namespace = '';
        if (preg_match('/^namespace\s+(.+?);/m', $content, $matches)) {
            $namespace = $matches[1];
        }

        $class = '';
        if (preg_match('/^class\s+([a-zA-Z0-9_]+)/m', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }
}
