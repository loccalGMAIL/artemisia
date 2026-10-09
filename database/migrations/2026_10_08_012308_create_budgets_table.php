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
        Schema::create('budgets', function (Blueprint $table) {
            // The id is the correlative identifier shown to users (plan D-1).
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('title', 150);
            $table->string('modality', 10);
            $table->date('issue_date');
            $table->date('validity_date');
            $table->string('status', 10)->default('draft');
            $table->date('response_date')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            // Denormalized totals, recalculated on every change (plan D-2).
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            // Discarded budgets are soft deleted (plan D-4).
            $table->softDeletes();

            // Plan section 4. Explicit because SQLite does not index foreign keys.
            $table->index('client_id');
            $table->index('status');
            $table->index('deleted_at');
            $table->index('created_at');
            $table->index('validity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
