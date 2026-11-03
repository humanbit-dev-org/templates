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
        Schema::create('scheduled_job_runs', function (Blueprint $table) {
            $table->id();
            // Artisan command name (sftp:processa) or job class: the key the
            // Scheduled Jobs page uses to tie a run to its schedule row.
            $table->string('name', 191)->comment('Artisan command name or job class');
            $table->string('trigger', 20)->comment('schedule | manual');
            $table->boolean('dry_run')->default(false);
            $table->string('status', 20)->comment('queued | running | success | failed | skipped');
            $table->json('parameters')->nullable()->comment('Arguments and options of a manual run');
            $table->integer('exit_code')->nullable();
            // Only the tail: enough to read what went wrong, not a log store.
            $table->text('output')->nullable()->comment('Last lines of the command output');
            $table->string('triggered_by', 191)->nullable()->comment('Backpack user who launched a manual run');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_job_runs');
    }
};
