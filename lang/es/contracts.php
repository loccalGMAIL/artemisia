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
    'validation' => [
        'budget_not_accepted' => 'Solo se puede crear un contrato a partir de un presupuesto aceptado.',
        'budget_not_monthly' => 'Solo se puede crear un contrato a partir de un presupuesto de abono mensual.',
        'budget_zero_amount' => 'El presupuesto tiene un importe mensual de cero: no hay nada que cobrar.',
        'budget_has_contract' => 'El presupuesto ya tiene el contrato #:id.',
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
