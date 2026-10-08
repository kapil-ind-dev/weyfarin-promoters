<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();

            // Your existing listings table. Rename if yours is `tours`, `listings`, etc.
            $table->foreignId('experience_id')->constrained('experiences')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(0);   // drag-and-drop order in the dashboard
            $table->string('badge', 24)->nullable();                // "Staff pick", "New", "Only 2 left"
            $table->json('overrides')->nullable();                  // {title, image, price_from} — per-widget copy tweaks
            $table->timestamps();

            $table->unique(['widget_id', 'experience_id']);
            $table->index(['widget_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_items');
    }
};
