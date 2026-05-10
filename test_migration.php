<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BrokenMigration extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint ) {
            ->id();
            ->string('name');
            ->timestamps()
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}
