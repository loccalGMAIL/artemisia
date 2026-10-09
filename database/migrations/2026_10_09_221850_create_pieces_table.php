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
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets');
            // Informative link, deliberately without a foreign key: removing the source item
            // must not fail nor alter an existing piece (RF-8, plan D-7). Null on loose pieces.
            $table->unsignedBigInteger('budget_item_id')->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('work_category_id')->constrained('work_categories');
            $table->string('status', 20)->default('pending');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            // Discarded pieces are soft deleted (plan D-1).
            $table->softDeletes();

            // Plan section 4. Explicit because SQLite does not index foreign keys.
            $table->index('budget_id');
            $table->index('budget_item_id');
            $table->index('status');
            $table->index('assignee_id');
            $table->index('due_date');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};
