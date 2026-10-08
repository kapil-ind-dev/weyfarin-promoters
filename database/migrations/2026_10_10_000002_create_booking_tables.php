<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();              // WEY-482913
            $table->string('access_token_hash', 64);                // sha256 of the token the widget holds

            $table->foreignId('widget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('experience_id')->constrained();
            $table->foreignId('departure_id')->constrained('experience_departures');

            // Guest checkout — filled in on the checkout page, before payment.
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 32)->nullable();

            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);

            // Money: integer minor units (PLAN.md D11), frozen at booking time.
            $table->unsignedInteger('adult_unit_minor');
            $table->unsignedInteger('child_unit_minor');
            $table->unsignedInteger('gross_minor');
            $table->char('currency', 3);

            // Rate snapshot in basis points (PLAN.md D12). 7500 = 75%.
            $table->unsignedSmallInteger('provider_share_bp');
            $table->unsignedSmallInteger('partner_commission_bp');

            // pending → confirmed | expired | released | failed | refund_due
            $table->string('status', 16)->default('pending');
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->string('idempotency_key', 64);
            $table->string('source_host', 191)->nullable();
            $table->timestamps();

            $table->unique(['widget_id', 'idempotency_key']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_intent_id')->unique();
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('requires_payment');   // requires_payment | succeeded | failed | canceled
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        // APPEND ONLY (PLAN.md D13). A reversal is a new negative row.
        Schema::create('booking_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('party_type', 16);                        // provider | partner | platform
            $table->unsignedBigInteger('party_id')->nullable();      // provider has no table in the POC yet
            $table->integer('amount_minor');                         // signed: reversals are negative
            $table->unsignedSmallInteger('basis_points')->nullable();
            $table->string('status', 16)->default('accrued');        // accrued | payable | paid | reversed
            $table->timestamps();

            $table->index(['party_type', 'party_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_splits');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
    }
};
