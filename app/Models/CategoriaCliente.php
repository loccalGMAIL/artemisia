<?php

namespace App\Models;

use App\Models\Concerns\RegistraActividad;
use Database\Factories\CategoriaClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaCliente extends Model
{
    /** @use HasFactory<CategoriaClienteFactory> */
    use HasFactory, RegistraActividad;

    protected $table = 'categorias_cliente';

    protected $fillable = [
        'nombre',
        'descripcion',
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
     * @return HasMany<Cliente, $this>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * @return HasMany<PrecioServicio, $this>
     */
    public function preciosServicio(): HasMany
    {
        return $this->hasMany(PrecioServicio::class);
    }
}
