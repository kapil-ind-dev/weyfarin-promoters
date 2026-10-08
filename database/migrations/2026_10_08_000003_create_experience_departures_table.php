<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Availability. The booking flow (Day 15) holds and sells seats here.
        // In the live DB the provider panel owns this table.
        Schema::create('experience_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time', 5)->nullable();               // "06:30"
            $table->unsignedSmallInteger('seats_total');
            $table->unsignedSmallInteger('seats_held')->default(0);    // checkout in progress, 10-min TTL
            $table->unsignedSmallInteger('seats_sold')->default(0);
            $table->unsignedInteger('price_override_minor')->nullable(); // minor units (PLAN.md D11)
            $table->string('status', 12)->default('open');             // open | closed | cancelled
            $table->timestamps();

            $table->unique(['experience_id', 'date', 'start_time']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_departures');
    }
};
