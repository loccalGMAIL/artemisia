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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            // One contract per budget (RF-5).
            $table->foreignId('budget_id')->unique()->constrained('budgets');
            // Copied from the budget when the contract is created (RF-6).
            $table->foreignId('client_id')->constrained('clients');
            $table->date('start_date');
            // Null means no end date: an indefinite contract (RF-8).
            $table->unsignedSmallInteger('term_months')->nullable();
            // First day of the last month of a fixed term; null when indefinite (plan D-12).
            $table->date('term_end_month')->nullable();
            $table->unsignedTinyInteger('due_day');
            $table->decimal('monthly_amount', 12, 2);
            $table->string('status', 10)->default('active');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            // Contracts are never deleted for good (RF-34).
            $table->softDeletes();

            // Plan section 4. Explicit because SQLite does not index foreign keys.
            $table->index('client_id');
            $table->index(['status', 'term_end_month']);
            $table->index('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
