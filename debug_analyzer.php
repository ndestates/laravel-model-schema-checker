<?php
require 'vendor/autoload.php';

 = new NDEstates\LaravelModelSchemaChecker\Services\MigrationCriticalityAnalyzer();
 = sys_get_temp_dir() . '/test_' . uniqid();
 =  . '/database/migrations';
mkdir(, 0755, true);

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
            ->timestamps()
        });
    }
}';

file_put_contents( . '/test.php', );
echo 'File written: ' .  . '/test.php' . PHP_EOL;
echo 'File exists: ' . (file_exists( . '/test.php') ? 'YES' : 'NO') . PHP_EOL;
echo 'Content length: ' . strlen(file_get_contents( . '/test.php')) . PHP_EOL;

 = ->analyzeMigrations();
echo 'Issues found: ' . ['issues_found'] . PHP_EOL;
echo 'Critical issues: ' . count(['criticality']['CRITICAL']) . PHP_EOL;
