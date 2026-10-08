<?php

return [
    'modalities' => [
        'single' => 'Pago único',
        'monthly' => 'Abono mensual',
    ],
    'statuses' => [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
    ],
    'discount_types' => [
        'percentage' => 'Porcentaje',
        'fixed' => 'Monto fijo',
    ],
    'history_fields' => [
        'item_added' => 'Ítem agregado',
        'item_updated' => 'Ítem modificado',
        'item_removed' => 'Ítem quitado',
        'discount_changed' => 'Descuento',
        'status_changed' => 'Estado',
        'header_changed' => 'Datos de cabecera',
    ],
    'validation' => [
        'service_name_conflict' => 'Ya existe un servicio con el nombre «:name».',
    ],
];
