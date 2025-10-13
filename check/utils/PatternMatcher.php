<?php

namespace Check\Utils;

class PatternMatcher
{
    public static function guessTypeFromFieldName(string $fieldName): array
    {
        $fieldName = strtolower($fieldName);
        
        $patterns = [
            // Foreign keys
            '/.*_id$/' => ['method' => 'foreignId', 'params' => []],
            
            // Timestamps
            '/.*_at$/' => ['method' => 'timestamp', 'params' => []],
            '/.*_date$/' => ['method' => 'date', 'params' => []],
            '/.*_time$/' => ['method' => 'time', 'params' => []],
            
            // Boolean fields
            '/^is_.*/' => ['method' => 'boolean', 'params' => []],
            '/^has_.*/' => ['method' => 'boolean', 'params' => []],
            '/^can_.*/' => ['method' => 'boolean', 'params' => []],
            '/^should_.*/' => ['method' => 'boolean', 'params' => []],
            '/(active|enabled|published|verified|confirmed)$/' => ['method' => 'boolean', 'params' => []],
            
            // Numeric fields
            '/(price|amount|cost|total|sum|balance)/' => ['method' => 'decimal', 'params' => [10, 2]],
            '/(count|number|quantity|qty|position|order|sort)/' => ['method' => 'integer', 'params' => []],
            '/(rating|score|percentage|percent)/' => ['method' => 'decimal', 'params' => [5, 2]],
            
            // Text fields
            '/(description|content|body|text|comment|note|message)/' => ['method' => 'text', 'params' => []],
            '/(title|name|label|slug|code)/' => ['method' => 'string', 'params' => [255]],
            
            // Contact information
            '/(email|mail)/' => ['method' => 'string', 'params' => [255]],
            '/(phone|mobile|tel|fax)/' => ['method' => 'string', 'params' => [20]],
            '/(url|link|website)/' => ['method' => 'string', 'params' => [255]],
            
            // Address fields
            '/(address|street|city|town|state|country|postal|zip)/' => ['method' => 'string', 'params' => [255]],
            
            // JSON fields
            '/(config|settings|options|meta|data|payload)/' => ['method' => 'json', 'params' => []],
            
            // UUIDs
            '/uuid/' => ['method' => 'uuid', 'params' => []],
        ];
        
        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $fieldName)) {
                return $type;
            }
        }
        
        // Default fallback
        return ['method' => 'string', 'params' => [255]];
    }
    
    public static function isLikelyForeignKey(string $fieldName): bool
    {
        return (bool) preg_match('/.*_id$/', strtolower($fieldName));
    }
    
    public static function isLikelyBoolean(string $fieldName): bool
    {
        return (bool) preg_match('/^(is_|has_|can_|should_).*|(active|enabled|published|verified|confirmed)$/', strtolower($fieldName));
    }
}