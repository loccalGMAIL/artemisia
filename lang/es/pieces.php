<?php

return [
    'model' => 'pieza',
    'plural' => 'Piezas',
    'fields' => [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'category' => 'Categoría de trabajo',
        'budget' => 'Presupuesto',
        'status' => 'Estado',
        'due_date' => 'Fecha de entrega',
        'proposal' => 'Piezas a crear',
    ],
    'actions' => [
        'generate' => 'Generar piezas',
        'proposal_help' => 'Una pieza por ítem. Duplique una línea para dividir su cantidad en varias piezas, o quítela si no hace falta.',
        'create_loose' => 'Crear pieza suelta',
    ],
    'notifications' => [
        'generated' => '{1} Se creó :count pieza.|[2,*] Se crearon :count piezas.',
        'created' => 'Pieza creada.',
    ],
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
        'lines_invalid' => 'Cada pieza necesita un nombre y una categoría de trabajo válidos.',
        'category_required' => 'Debe elegir la categoría de trabajo de la pieza.',
        'category_missing' => 'La categoría de trabajo elegida no existe.',
        'assignee_invalid' => 'La pieza solo se puede delegar a una cuenta con rol staff o admin.',
        'piece_delivered' => 'La pieza ya está entregada y no admite cambios.',
        'file_required' => 'Debe adjuntar un archivo para enviar la pieza a aprobación.',
        'file_format' => 'El archivo debe ser JPG, PNG, PDF o MP4.',
        'file_size' => 'El archivo no puede superar los 100 MB.',
        'rejection_reason_max' => 'El motivo del rechazo admite hasta 500 caracteres.',
        'not_discardable' => 'Solo se puede descartar una pieza pendiente.',
        'invalid_transition' => 'No se puede pasar una pieza de «:from» a «:to».',
    ],
];
