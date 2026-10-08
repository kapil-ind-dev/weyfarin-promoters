<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If weyfarin.com already has a listings table, skip this migration and
        // point the FKs in the widget migrations at your real table instead.
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();

            $table->string('city')->nullable();
            $table->string('country')->nullable();

            $table->unsignedInteger('duration_minutes')->nullable();   // 4320 = 3 days
            $table->decimal('price_from', 10, 2)->default(0);
            $table->char('currency', 3)->default('INR');

            $table->decimal('rating', 3, 2)->nullable();               // 4.80
            $table->unsignedInteger('review_count')->default(0);

            $table->string('cover_image')->nullable();                 // fallback when no media rows
            $table->foreignId('category_id')->nullable();

            $table->string('status', 20)->default('draft');            // draft | published | archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['city', 'country']);
        });

        Schema::create('experience_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['experience_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_media');
        Schema::dropIfExists('experiences');
    }
};
