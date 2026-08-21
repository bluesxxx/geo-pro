<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_runs')) {
            return;
        }

        Schema::create('audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('url', 2048);
            $table->string('status', 20)->default('processing'); // processing|completed|failed
            $table->unsignedSmallInteger('score')->nullable();
            $table->boolean('missing_faq')->default(false);
            $table->boolean('missing_schema')->default(false);
            $table->json('suggestions')->nullable();
            $table->json('raw_features')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'audit_runs_status_created_idx');
            $table->index(['url', 'created_at'], 'audit_runs_url_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_runs');
    }
};
