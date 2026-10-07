<?php

return [
    'person_types' => [
        'individual' => 'Persona física',
        'company' => 'Persona jurídica',
    ],
    'document_labels' => [
        'individual' => 'DNI',
        'company' => 'CUIT',
    ],
    'statuses' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],
    'validation' => [
        'invalid_dni' => 'El DNI debe tener 7 u 8 dígitos numéricos.',
        'invalid_cuit' => 'El CUIT debe tener 11 dígitos numéricos con dígito verificador válido.',
        'person_type_immutable' => 'El tipo de persona no se puede cambiar: debe darse de alta un cliente nuevo.',
        'document_conflict' => 'Ya existe un cliente con el documento :document (:name).',
    ],
    'history_fields' => [
        'identification' => 'Identificación',
        'address' => 'Domicilio',
        'contacts' => 'Contactos',
        'status' => 'Estado',
        'archived' => 'Archivado',
        'account_link' => 'Cuenta vinculada',
    ],
];
