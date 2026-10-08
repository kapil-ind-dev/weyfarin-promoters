<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();

            // Owner. Null = created by Weyfarin staff for a generic embed.
            // Point this at your partners/users table.
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            // Public, exposed in the embed snippet. Never secret.
            // Format: pk_live_<32 hex>  /  pk_test_<32 hex>
            $table->string('public_key', 48)->unique();

            $table->string('name');                       // internal label, e.g. "Nomad Blog – Himalaya picks"

            // --- selection -------------------------------------------------
            $table->string('selection_mode', 16)->default('manual');   // manual | dynamic
            $table->json('filters')->nullable();                       // dynamic: {city, country, category_ids, tag_ids, min_rating, price_max}
            $table->string('sort', 20)->default('curated');            // curated | price_asc | price_desc | rating_desc | newest
            $table->unsignedTinyInteger('max_items')->default(6);

            // --- presentation ----------------------------------------------
            $table->string('layout', 16)->default('grid');             // grid | carousel | list
            $table->unsignedTinyInteger('columns')->default(3);        // desktop columns; widget downshifts on narrow containers
            $table->json('theme')->nullable();                         // {accent,text,muted,surface,border,radius,font,card_shadow}
            $table->json('show')->nullable();                          // {price,rating,duration,location,cta,badge}
            $table->string('cta_label', 40)->default('View details');
            $table->string('link_target', 10)->default('_blank');      // _blank | _self
            $table->string('heading')->nullable();                     // optional widget title above the cards

            // --- attribution -----------------------------------------------
            $table->json('utm')->nullable();                           // {source,medium,campaign} appended to every outbound link
            $table->string('affiliate_code', 40)->nullable();

            // --- ops ---------------------------------------------------------
            $table->boolean('is_active')->default(true);
            $table->boolean('restrict_domains')->default(false);       // enforce widget_domains allowlist
            $table->unsignedInteger('config_version')->default(1);     // bump on save -> busts CDN cache
            $table->timestamp('last_served_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['partner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
