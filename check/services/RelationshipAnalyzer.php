<?php

namespace Check\Services;

use Check\Utils\Logger;

class RelationshipAnalyzer
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function analyzeModelRelationships(string $modelClass): array
    {
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($modelClass);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            $relationships = [];

            foreach ($methods as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass ||
                    strpos($method->getName(), '__') === 0) {
                    continue;
                }

                try {
                    $relationshipInfo = $this->analyzeMethod($method, $model);
                    if ($relationshipInfo) {
                        $relationships[$method->getName()] = $relationshipInfo;
                    }
                } catch (\Exception $e) {
                    // Skip methods that cause errors during analysis
                    continue;
                }
            }

            return $relationships;

        } catch (\Exception $e) {
            $this->logger->error("Error analyzing relationships for $modelClass: " . $e->getMessage());
            return [];
        }
    }

    private function analyzeMethod(\ReflectionMethod $method, $model): ?array
    {
        try {
            $sourceCode = $this->getMethodSourceCode($method);
            if (!$sourceCode) return null;

            $relationshipType = $this->detectRelationshipType($sourceCode);
            if (!$relationshipType) return null;

            $relationship = $method->invoke($model);

            if ($relationship instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                try {
                    $relatedModel = get_class($relationship->getRelated());
                    // Verify the related model class exists
                    if (!class_exists($relatedModel)) {
                        return null;
                    }

                    return [
                        'type' => $relationshipType,
                        'related_model' => $relatedModel,
                        'foreign_key' => $relationship->getForeignKeyName(),
                        'local_key' => $relationship->getLocalKeyName(),
                        'table' => $relationship->getRelated()->getTable(),
                    ];
                } catch (\Exception $e) {
                    // Skip relationships that can't be analyzed
                    return null;
                }
            }

        } catch (\Exception $e) {
            // Skip methods that can't be analyzed
        }

        return null;
    }

    private function getMethodSourceCode(\ReflectionMethod $method): ?string
    {
        try {
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if (!$filename || !$startLine || !$endLine) {
                return null;
            }

            $lines = file($filename);
            $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

            return implode('', $methodLines);

        } catch (\Exception $e) {
            return null;
        }
    }

    private function detectRelationshipType(string $sourceCode): ?string
    {
        $patterns = [
            'belongsTo' => '/\\$this->belongsTo\\s*\\(/i',
            'hasOne' => '/\\$this->hasOne\\s*\\(/i',
            'hasMany' => '/\\$this->hasMany\\s*\\(/i',
            'belongsToMany' => '/\\$this->belongsToMany\\s*\\(/i',
            'hasManyThrough' => '/\\$this->hasManyThrough\\s*\\(/i',
            'hasOneThrough' => '/\\$this->hasOneThrough\\s*\\(/i',
            'morphOne' => '/\\$this->morphOne\\s*\\(/i',
            'morphMany' => '/\\$this->morphMany\\s*\\(/i',
            'morphTo' => '/\\$this->morphTo\\s*\\(/i',
            'morphToMany' => '/\\$this->morphToMany\\s*\\(/i',
            'morphedByMany' => '/\\$this->morphedByMany\\s*\\(/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $sourceCode)) {
                return $type;
            }
        }

        return null;
    }

    public function hasInverseRelationship(string $originalModel, string $relationshipName, array $relationshipInfo): bool
    {
        $relatedModelClass = $relationshipInfo['related_model'];
        if (!class_exists($relatedModelClass)) {
            return false; // Related model doesn't exist
        }

        $inverseRelationships = $this->analyzeModelRelationships($relatedModelClass);

        foreach ($inverseRelationships as $inverseRelName => $inverseRelInfo) {
            // Check if the inverse relationship points back to the original model
            if ($inverseRelInfo['related_model'] !== $originalModel) {
                continue;
            }

            // Check for matching relationship types
            $isInverse = $this->isInversePair($relationshipInfo['type'], $inverseRelInfo['type']);

            if ($isInverse) {
                return true;
            }
        }

        return false;
    }

    private function isInversePair(string $type1, string $type2): bool
    {
        $inversePairs = [
            'hasOne' => 'belongsTo',
            'hasMany' => 'belongsTo',
            'belongsTo' => 'hasMany', // or hasOne, but hasMany is more common
            'belongsToMany' => 'belongsToMany',
            'morphOne' => 'morphTo',
            'morphMany' => 'morphTo',
            'morphTo' => 'morphMany', // or morphOne
            'morphToMany' => 'morphedByMany',
            'morphedByMany' => 'morphToMany',
        ];

        return isset($inversePairs[$type1]) && $inversePairs[$type1] === $type2;
    }
}
