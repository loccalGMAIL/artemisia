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
        Schema::create('piece_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained('pieces');
            $table->string('field', 20);
            $table->json('old_value')->nullable();
            $table->json('new_value');
            $table->foreignId('author_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['piece_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piece_histories');
    }
};
