<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->nullableMorphs('owner');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('live_version_id')->nullable();
            $table->boolean('paused')->default(false);
            $table->timestamps();
        });

        Schema::create('flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->nullableMorphs('author');
            $table->nullableMorphs('approver');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'version']);
        });

        Schema::create('flow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_version_id')->constrained('flow_versions')->cascadeOnDelete();
            $table->uuid('correlation_id')->index();
            $table->json('payload')->nullable();
            $table->json('context')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('dry_run')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('flow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_run_id')->constrained('flow_runs')->cascadeOnDelete();
            $table->string('step_id');
            $table->string('brick');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamps();

            $table->index(['flow_run_id', 'step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_run_steps');
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flow_versions');
        Schema::dropIfExists('flows');
    }
};
