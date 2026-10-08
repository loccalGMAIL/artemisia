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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Unique over every service, deactivated ones included (plan D-3, RF-3).
            $table->string('name_normalized', 150)->virtualAs('LOWER(TRIM(name))')->unique();
            $table->text('description')->nullable();
            $table->foreignId('work_category_id')->constrained('work_categories');
            $table->decimal('list_price', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('work_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
