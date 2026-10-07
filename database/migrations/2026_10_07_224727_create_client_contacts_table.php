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
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('role', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            // Holds client_id only for the primary contact, so a UNIQUE index on it allows
            // at most one primary per client (plan D-8). CASE works on MySQL and SQLite.
            $table->unsignedBigInteger('primary_marker')
                ->virtualAs('CASE WHEN is_primary = 1 THEN client_id ELSE NULL END')
                ->nullable()
                ->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
