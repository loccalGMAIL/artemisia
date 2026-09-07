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
        Schema::create('precios_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('categoria_cliente_id')->nullable()->constrained('categorias_cliente')->cascadeOnDelete();
            $table->decimal('precio', 12, 2);
            $table->date('vigente_desde');
            $table->timestamps();

            $table->index(['servicio_id', 'categoria_cliente_id', 'vigente_desde'], 'precios_servicio_vigencia_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precios_servicio');
    }
};
