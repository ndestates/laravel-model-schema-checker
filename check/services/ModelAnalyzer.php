<?php

namespace Check\Services;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Model;

class ModelAnalyzer
{
    private CheckConfig $config;
    private Logger $logger;
    
    public function __construct(CheckConfig $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    public function getAllModelClasses(): array
    {
        $modelClasses = [];
        $files = File::allFiles($this->config->getModelsDir());
        
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $className = 'App\\Models\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            
            if (class_exists($className) && is_subclass_of($className, Model::class)) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->isAbstract()) {
                    $modelClasses[$className] = $file->getRealPath();
                }
            }
        }
        
        $this->logger->info("Found " . count($modelClasses) . " model classes");
        return $modelClasses;
    }
    
    public function getModelFields(string $modelClass): array
    {
        try {
            $model = new $modelClass();
            return array_diff($model->getFillable(), $this->config->getExcludedFields());
        } catch (\Exception $e) {
            $this->logger->error("Error inspecting model $modelClass: {$e->getMessage()}");
            return [];
        }
    }
    
    public function getModelCasts(string $modelClass): array
    {
        try {
            $model = new $modelClass();
            return $model->getCasts();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getTableName(string $modelClass): string
    {
        $model = new $modelClass();
        return $model->getTable();
    }
    
    public function updateModelFile(string $filePath, array $newFillableFields): bool
    {
        try {
            $content = file_get_contents($filePath);
            
            // Generate new fillable array
            $fillableArray = "    protected \$fillable = [\n";
            foreach ($newFillableFields as $field) {
                $fillableArray .= "        '$field',\n";
            }
            $fillableArray .= "    ];";
            
            // Replace existing fillable array
            $pattern = '/protected\\s+\\$fillable\\s*=\\s*\\[[^\\]]*\\];/s';
            $newContent = preg_replace($pattern, $fillableArray, $content);
            
            if ($newContent && $newContent !== $content) {
                file_put_contents($filePath, $newContent);
                $this->logger->success("Updated model file: $filePath");
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Failed to update model file $filePath: {$e->getMessage()}");
            return false;
        }
    }
}