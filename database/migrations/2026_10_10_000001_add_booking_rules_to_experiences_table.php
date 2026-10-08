<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // Child price as a percentage of the adult price. NULL = same as
            // adult. A percentage follows departure price overrides on its own,
            // so there is never a second number to keep in sync.
            $table->unsignedTinyInteger('child_price_percent')->nullable();

            // NULL = all ages. Above 12 means children (2–12) cannot book at all.
            $table->unsignedTinyInteger('min_age')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['child_price_percent', 'min_age']);
        });
    }
};
