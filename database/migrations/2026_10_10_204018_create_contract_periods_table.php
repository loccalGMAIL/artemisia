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
        Schema::create('contract_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts');
            // Copied from the contract so the lists filter by client without a join (plan D-13).
            $table->foreignId('client_id')->constrained('clients');
            // First day of the month that is charged.
            $table->date('month');
            $table->date('due_date');
            // The contract's monthly amount when the period was generated (RF-40).
            $table->decimal('fee', 12, 2);
            // Fee plus extras minus discounts, and the sum of the valid payments: stored and
            // recalculated on every change; balance and payment status are derived (plan D-3).
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamps();

            // At most one period per contract and month (RF-38), plus the list filters (RNF-3).
            $table->unique(['contract_id', 'month']);
            $table->index('client_id');
            $table->index('due_date');
            $table->index('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_periods');
    }
};
