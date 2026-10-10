<?php

return [
    'system' => 'Sistema',
    'statuses' => [
        'active' => 'Vigente',
        'finished' => 'Finalizado',
        'cancelled' => 'Cancelado',
    ],
    'concept_types' => [
        'extra' => 'Extra',
        'discount' => 'Descuento',
    ],
    'payment_methods' => [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'other' => 'Otro',
    ],
    'payment_statuses' => [
        'pending' => 'Pendiente',
        'partial' => 'Parcial',
        'paid' => 'Pagado',
    ],
    'history_fields' => [
        'created' => 'Alta',
        'status_changed' => 'Estado',
        'amount_changed' => 'Importe mensual',
        'due_day_changed' => 'Día de vencimiento',
    ],
    'period_history_fields' => [
        'generated' => 'Período generado',
        'concept_added' => 'Concepto agregado',
        'concept_removed' => 'Concepto quitado',
        'invoice_changed' => 'Referencia de factura',
        'payment_registered' => 'Pago registrado',
        'payment_voided' => 'Pago anulado',
    ],
];
