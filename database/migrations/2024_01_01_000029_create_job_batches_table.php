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
        Schema::create('job_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('total_jobs');
            $table->string('pending_jobs');
            $table->string('failed_jobs');
            $table->text('failed_job_ids');
            $table->text('options')->nullable();
            $table->string('cancelled_at')->nullable();
            $table->string('created_at');
            $table->string('finished_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_batches');
    }
};
