<?php

namespace App\Filament\Cliente\Widgets;

use App\Enums\EstadoContrato;
use App\Enums\EstadoPago;
use App\Models\Contrato;
use App\Models\ResumenMensual;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ResumenClienteWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $clienteId = auth()->user()->cliente_id;

        $contratosActivos = Contrato::query()
            ->whereHas('presupuesto', fn (Builder $query) => $query->where('cliente_id', $clienteId))
            ->where('estado', EstadoContrato::Activo)
            ->count();

        $proximoResumen = ResumenMensual::query()
            ->whereHas('contrato.presupuesto', fn (Builder $query) => $query->where('cliente_id', $clienteId))
            ->where('estado_pago', EstadoPago::Pendiente)
            ->orderBy('periodo')
            ->first();

        return [
            Stat::make('Contratos activos', $contratosActivos),
            Stat::make(
                'Próximo resumen pendiente de pago',
                $proximoResumen
                    ? "{$proximoResumen->periodo} — \${$proximoResumen->monto_total}"
                    : 'Ninguno'
            ),
        ];
    }
}
