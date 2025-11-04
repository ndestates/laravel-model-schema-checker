<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('check_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Remove foreign key for flexibility
            $table->string('job_id')->nullable()->index();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->json('check_types')->nullable(); // Types of checks run
            $table->json('options')->nullable(); // Options used for the check
            $table->json('issues')->nullable(); // All issues found
            $table->json('stats')->nullable(); // Statistics about the check
            $table->integer('total_issues')->default(0);
            $table->integer('critical_issues')->default(0);
            $table->integer('warning_issues')->default(0);
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('applied_fixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_result_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('user_id'); // Remove foreign key for flexibility
            $table->string('fix_title');
            $table->text('fix_description');
            $table->string('file_path')->nullable();
            $table->string('improvement_class')->nullable();
            $table->json('fix_data')->nullable(); // Additional data about the fix
            $table->boolean('can_rollback')->default(false);
            $table->json('rollback_data')->nullable(); // Data needed for rollback
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['user_id', 'applied_at']);
            $table->index(['check_result_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applied_fixes');
        Schema::dropIfExists('check_results');
    }
};