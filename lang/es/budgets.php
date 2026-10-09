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
        'service_inactive' => 'El servicio está desactivado y no se puede agregar a un presupuesto.',
        'items_limit' => 'Un presupuesto admite hasta :max ítems.',
        'budget_not_editable' => 'El presupuesto no admite cambios: solo se modifica en borrador o enviado.',
        'client_required' => 'Debe elegir el cliente destinatario del presupuesto.',
        'client_missing' => 'El cliente elegido no existe.',
        'validity_after_issue' => 'La fecha de validez debe ser posterior a la fecha de emisión.',
        'service_not_deletable' => 'Los servicios no se eliminan: solo pueden desactivarse.',
    ],
    'services' => [
        'model' => 'servicio',
        'plural' => 'Servicios',
        'fields' => [
            'name' => 'Nombre',
            'description' => 'Descripción',
            'category' => 'Categoría',
            'list_price' => 'Precio de lista',
            'is_active' => 'Activo',
        ],
        'actions' => [
            'activate' => 'Reactivar',
            'deactivate' => 'Desactivar',
        ],
        'notifications' => [
            'created' => 'Servicio creado.',
            'saved' => 'Servicio actualizado.',
            'activated' => 'Servicio reactivado.',
            'deactivated' => 'Servicio desactivado.',
        ],
        'price_histories' => [
            'title' => 'Historial de precios',
            'date' => 'Fecha y hora',
            'old_price' => 'Precio anterior',
            'new_price' => 'Precio nuevo',
            'author' => 'Autor',
        ],
    ],
];
