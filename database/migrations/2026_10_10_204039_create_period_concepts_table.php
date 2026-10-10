<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('period_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_period_id')->constrained('contract_periods');
            // Extra or discount; the sign comes from the type, the amount is always positive.
            $table->string('type', 10);
            // The description of an extra, or the justification of a discount (RF-42, RF-43).
            $table->string('description', 255);
            $table->decimal('amount', 10, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            // A removed concept is deleted and kept as a snapshot in the history (plan D-5).

            $table->index('contract_period_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_concepts');
    }
};
