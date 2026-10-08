<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // Where the experience starts / meets. 7 decimals ≈ 1 cm — more
            // than enough; 4 would do (≈ 11 m).
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Bounding-box prefilter for location widgets. Exact distance is
            // computed in PHP afterwards — SQLite has no trig functions, and
            // this keeps the query identical on MySQL.
            $table->index(['latitude', 'longitude']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['category_id']);
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
