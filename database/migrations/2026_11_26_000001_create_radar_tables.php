<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('radar_sources')) {
            Schema::create('radar_sources', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('source_type', 30)->default('rss');
                $table->string('url', 2048);
                $table->string('topic', 50)->nullable();
                $table->string('cluster', 50)->nullable();
                $table->text('note')->nullable();
                $table->unsignedInteger('pull_interval_hours')->default(24);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_pulled_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedInteger('error_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['team_id', 'is_active']);
                $table->index(['is_active', 'last_pulled_at']);
            });
        }

        if (! Schema::hasTable('radar_items')) {
            Schema::create('radar_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('radar_sources')->cascadeOnDelete();
                $table->char('fingerprint', 40);
                $table->string('title', 1000);
                $table->string('url', 2048)->nullable();
                $table->text('excerpt')->nullable();
                $table->string('author')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('fetched_at');
                $table->timestamps();

                $table->unique(['source_id', 'fingerprint']);
                $table->index(['team_id', 'published_at']);
                $table->index(['team_id', 'fetched_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('radar_items');
        Schema::dropIfExists('radar_sources');
    }
};
