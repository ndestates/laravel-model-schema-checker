<?php

$files = glob('/home/nickd/projects/laravel-model-schema-checker/tests/Feature/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Add namespace and class if not present
    if (!preg_match('/^<\?php\s*\n\nnamespace/', $content)) {
        $content = preg_replace('/^<\?php\s*\n\ndescribe\(/', "<?php\n\nnamespace NDEstates\\LaravelModelSchemaChecker\\Tests\\Feature;\n\nuse PHPUnit\\Framework\\TestCase;\n\nclass " . basename($file, '.php') . " extends TestCase\n{", $content);
    }
    
    // Remove describe
    $content = preg_replace('/\s*describe\([^)]+\)\s*{\s*/', "\n    // ", $content);
    $content = preg_replace('/\s*}\);\s*$/', "}\n}", $content);
    
    // Convert it to test methods
    $content = preg_replace_callback('/\s*it\(\'([^\']+)\',\s*function\s*\(\)\s*{/', function($matches) {
        $desc = $matches[1];
        $method = 'test' . str_replace(' ', '', ucwords(str_replace([' ', '-'], ' ', $desc)));
        return "\n    public function $method()\n    {";
    }, $content);
    
    // Convert expect to assert
    $content = preg_replace('/expect\(([^)]+)\)->toBe\(([^)]+)\)/', '$this->assertEquals($2, $1)', $content);
    $content = preg_replace('/expect\(([^)]+)\)->toBeNull\(\)/', '$this->assertNull($1)', $content);
    $content = preg_replace('/expect\(([^)]+)\)->toBeTrue\(\)/', '$this->assertTrue($1)', $content);
    $content = preg_replace('/expect\(([^)]+)\)->toBeFalse\(\)/', '$this->assertFalse($1)', $content);
    
    // Remove extra closing });
    $content = preg_replace('/\s*}\);\s*$/', '', $content);
    
    file_put_contents($file, $content);
}

echo "Conversion done.\n";