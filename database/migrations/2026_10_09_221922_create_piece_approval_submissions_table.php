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
        Schema::create('piece_approval_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained('pieces');
            $table->string('file_path', 255);
            $table->string('file_extension', 10);
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            // Null while the client has not answered yet (plan D-3).
            $table->string('resolution', 10)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            // No soft deletes: a submission is never removed (RF-30, RNF-3).
            $table->timestamps();

            // Plan section 4. Explicit because SQLite does not index foreign keys.
            $table->index('piece_id');
            $table->index('resolved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piece_approval_submissions');
    }
};
