<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProblematicColumn extends Migration
{
    public function up()
    {
        Schema::table('test_table', function (Blueprint $table) {
            $table->string('email')->nullable()->change(); // Data loss potential
            $table->dropColumn('name'); // Data loss
        });
    }

    public function down()
    {
        Schema::table('test_table', function (Blueprint $table) {
            $table->string('name');
            $table->string('email')->nullable(false)->change();
        });
    }
}