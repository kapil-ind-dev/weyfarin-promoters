<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->json('languages')->nullable();                     // ["English","Hindi"]
            $table->unsignedSmallInteger('group_size_max')->nullable();
            $table->string('meeting_point')->nullable();               // human label; coords already on the row

            // Host. In the live DB this is the provider's profile — map
            // these three to providers.* instead of duplicating them.
            $table->string('host_name')->nullable();
            $table->text('host_bio')->nullable();
            $table->string('host_avatar')->nullable();

            // "Things to keep in mind": [{title, body}, …] — plain text only,
            // the widget renders it with textContent.
            $table->json('good_to_know')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn([
                'languages', 'group_size_max', 'meeting_point',
                'host_name', 'host_bio', 'host_avatar', 'good_to_know',
            ]);
        });
    }
};
