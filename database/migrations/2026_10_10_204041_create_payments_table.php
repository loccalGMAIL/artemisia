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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_period_id')->constrained('contract_periods');
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 10);
            $table->string('reference', 100)->nullable();
            $table->foreignId('created_by')->constrained('users');
            // A voided payment is kept with who voided it, when and why (RF-77, plan D-6).
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_justification', 255)->nullable();
            $table->timestamps();
            // Payments are never deleted for good (RF-80); voiding is the functional removal.
            $table->softDeletes();

            $table->index(['contract_period_id', 'voided_at']);
            $table->index('paid_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
