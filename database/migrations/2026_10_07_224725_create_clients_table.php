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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('person_type', 20);
            $table->string('first_name', 150)->nullable();
            $table->string('last_name', 150)->nullable();
            $table->string('company_name', 150)->nullable();
            // Unique across archived clients too (plan D-4): not a partial index.
            $table->string('document', 11)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('street', 150)->nullable();
            $table->string('street_number', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('postal_code', 15)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
