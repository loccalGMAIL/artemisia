<?php

namespace App\Models;

use App\Models\Concerns\RegistraActividad;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory, RegistraActividad;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'cuit',
        'email',
        'telefono',
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
     * @return HasMany<CostoProveedor, $this>
     */
    public function costos(): HasMany
    {
        return $this->hasMany(CostoProveedor::class);
    }
}
