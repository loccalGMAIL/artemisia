<?php

return [
    'model' => 'contrato',
    'plural' => 'Contratos',
    'system' => 'Sistema',
    'fields' => [
        'start_date' => 'Fecha de inicio',
        'due_day' => 'Día de vencimiento',
        'indefinite' => 'Plazo indefinido',
        'term_months' => 'Plazo (meses)',
    ],
    'actions' => [
        'create' => 'Crear contrato',
        'due_day_help' => 'Día del mes, de 1 a 28, en que vence el pago de cada período.',
    ],
    'notifications' => [
        'created' => 'Contrato creado.',
    ],
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
        'start_date_required' => 'Debe indicar una fecha de inicio válida.',
        'due_day_range' => 'El día de vencimiento debe ser un número entero entre 1 y 28.',
        'term_months_invalid' => 'El plazo debe ser una cantidad entera de meses mayor que cero, o indefinido.',
        'term_elapsed' => 'El plazo del contrato ya transcurrió por completo.',
        'contract_not_deletable' => 'Los contratos no se eliminan: solo pueden cancelarse.',
        'period_not_deletable' => 'Los períodos no se eliminan.',
        'payment_not_deletable' => 'Los pagos no se eliminan: solo pueden anularse.',
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
