<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which hosts may render this widget. Normalised: lowercase, no scheme, no "www.".
        // Supports one level of wildcard: "*.partner.com".
        Schema::create('widget_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 191);
            $table->timestamps();

            $table->unique(['widget_id', 'domain']);
        });

        // Raw event stream. Keep ~90 days hot, roll up nightly into widget_stats_daily.
        Schema::create('widget_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained('experiences')->nullOnDelete();

            $table->string('type', 16);                  // impression | click
            $table->string('ref_host', 191)->nullable(); // where it was embedded
            $table->string('ref_path', 255)->nullable();
            $table->string('visitor_hash', 64)->nullable(); // sha256(ip + ua + daily salt) — no PII stored
            $table->string('device', 12)->nullable();       // mobile | tablet | desktop
            $table->char('country', 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['widget_id', 'type', 'created_at']);
            $table->index(['experience_id', 'created_at']);
        });

        // Pre-aggregated, this is what the dashboard actually reads.
        Schema::create('widget_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained('experiences')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);

            $table->unique(['widget_id', 'experience_id', 'date'], 'widget_stats_daily_unique');
            $table->index(['widget_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_stats_daily');
        Schema::dropIfExists('widget_events');
        Schema::dropIfExists('widget_domains');
    }
};
