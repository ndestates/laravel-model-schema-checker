<?php
 = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BrokenMigration extends Migration
{
    public function up()
    {
        Schema::create(\'users\', function (Blueprint ) {
            ->id();
            ->string(\'name\');
            // Missing closing parenthesis and semicolon
            ->timestamps()
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'users\');
    }
}';

echo 'Content length: ' . strlen() . PHP_EOL;
echo 'Pattern 1 (method call without semicolon): ' . (preg_match('/->\w+\s*\([^)]*\)\s*$/', ) ? 'YES' : 'NO') . PHP_EOL;
echo 'Pattern 2 (unclosed method call): ' . (preg_match('/->\w+\s*\([^)]*$/', ) ? 'YES' : 'NO') . PHP_EOL;
echo 'Pattern 3 (missing closing paren): ' . (preg_match('/\([^)]*$/', ) ? 'YES' : 'NO') . PHP_EOL;
echo 'Pattern 4 (missing semicolon): ' . (preg_match('/[^;]\s*$/', ) ? 'YES' : 'NO') . PHP_EOL;
