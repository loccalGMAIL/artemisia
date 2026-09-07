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
        Schema::create('resumenes_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('periodo', 7);
            $table->decimal('monto_base', 12, 2)->default(0);
            $table->decimal('monto_extras', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->string('estado_pago')->default('pendiente');
            $table->date('fecha_pago')->nullable();
            $table->boolean('enviado')->default(false);
            $table->timestamps();

            $table->unique(['contrato_id', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resumenes_mensuales');
    }
};
