<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use NDEstates\LaravelModelSchemaChecker\Contracts\CheckerInterface;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

class ApiResourceChecker extends BaseChecker implements CheckerInterface
{
    public function getName(): string
    {
        return 'API Resource Checker';
    }

    public function getDescription(): string
    {
        return 'Check Laravel API Resources for proper field mappings and transformations';
    }

    public function check(): array
    {
        $this->info('🔍 Checking API Resources...');

        $apiPath = app_path('Http/Resources');

        if (!$this->fileExists($apiPath)) {
            $this->warn("API Resources directory not found: {$apiPath}");
            return $this->issues;
        }

        $resourceFiles = File::allFiles($apiPath);

        foreach ($resourceFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkApiResourceFile($file);
            }
        }

        $this->displayResultsSummary();

        return $this->issues;
    }

    protected function checkApiResourceFile(\Symfony\Component\Finder\SplFileInfo $file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);

        if (!class_exists($className)) {
            $this->addIssue('api_resource', 'class_not_found', [
                'file' => $file->getPathname(),
                'class' => $className,
                'message' => "API Resource class '{$className}' could not be loaded"
            ]);
            return;
        }

        try {
            $reflection = new \ReflectionClass($className);

            if (!$reflection->isSubclassOf(\Illuminate\Http\Resources\Json\JsonResource::class)) {
                return; // Not a JsonResource
            }

            // Check if the resource has a proper toArray method
            $this->checkToArrayMethod($reflection, $file->getPathname());

            // Check for proper model relationship mappings
            $this->checkResourceFields($reflection, $file->getPathname());

        } catch (\Exception $e) {
            $this->addIssue('api_resource', 'reflection_error', [
                'file' => $file->getPathname(),
                'class' => $className,
                'message' => "Error analyzing API Resource: " . $e->getMessage()
            ]);
        }
    }

    protected function checkToArrayMethod(\ReflectionClass $reflection, string $filePath): void
    {
        if (!$reflection->hasMethod('toArray')) {
            $this->addIssue('api_resource', 'missing_to_array', [
                'file' => $filePath,
                'class' => $reflection->getName(),
                'message' => 'API Resource is missing toArray method'
            ]);
            return;
        }

        $method = $reflection->getMethod('toArray');

        if (!$method->isPublic()) {
            $this->addIssue('api_resource', 'to_array_not_public', [
                'file' => $filePath,
                'class' => $reflection->getName(),
                'message' => 'toArray method should be public'
            ]);
        }
    }

    protected function checkResourceFields(\ReflectionClass $reflection, string $filePath): void
    {
        // This would check for proper field mappings, relationships, etc.
        // For now, just a placeholder for future implementation
        $this->info("Checking resource fields for {$reflection->getName()}");
    }

    protected function getNamespaceFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }
}