<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $fillable = [
        'categoria_cliente_id',
        'nombre',
        'razon_social',
        'cuit',
        'email',
        'telefono',
        'direccion',
        'notas',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CategoriaCliente, $this>
     */
    public function categoriaCliente(): BelongsTo
    {
        return $this->belongsTo(CategoriaCliente::class);
    }

    /**
     * @return HasMany<Presupuesto, $this>
     */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }

    /**
     * @return HasManyThrough<Contrato, Presupuesto, $this>
     */
    public function contratos(): HasManyThrough
    {
        return $this->hasManyThrough(Contrato::class, Presupuesto::class);
    }
}
