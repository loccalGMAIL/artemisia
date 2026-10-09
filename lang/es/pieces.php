<?php

return [
    'statuses' => [
        'pending' => 'Pendiente',
        'in_production' => 'En producción',
        'in_review' => 'En revisión',
        'client_approval' => 'En aprobación del cliente',
        'approved' => 'Aprobada',
        'delivered' => 'Entregada',
    ],
    'resolutions' => [
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
    ],
    'history_fields' => [
        'created' => 'Alta',
        'status_changed' => 'Estado',
        'assignee_changed' => 'Responsable',
        'due_date_changed' => 'Fecha de entrega',
    ],
    'validation' => [
        'budget_not_accepted' => 'Solo se pueden generar piezas a partir de un presupuesto aceptado.',
    ],
];
