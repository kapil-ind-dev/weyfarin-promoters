<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "What you'll do" — ordered stops / days.
        Schema::create('experience_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();   // null for "Days 3–4" style entries
            $table->timestamps();

            $table->unique(['experience_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_itinerary_items');
    }
};
