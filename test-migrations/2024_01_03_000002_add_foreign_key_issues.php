<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyIssues extends Migration
{
    public function up()
    {
        Schema::table('test_table', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users'); // Missing constraint name and cascade
        });
    }

    public function down()
    {
        Schema::table('test_table', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
}