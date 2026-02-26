<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('pipeline_mode')->default('clean_only');
            $table->string('status')->default('pending'); // pending|running|done|failed
            $table->string('current_step')->nullable();   // cleaning|cross_referencing|enriching|generating_report
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->text('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_jobs');
    }
};