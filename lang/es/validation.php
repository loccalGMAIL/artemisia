<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'enum' => 'El campo :attribute seleccionado no es válido.',
    'max' => [
        'string' => 'El campo :attribute no debe superar los :max caracteres.',
    ],
    'password' => [
        'min' => 'La :attribute debe tener al menos :min caracteres.',
    ],
    'attributes' => [
        'password' => 'contraseña',
        'email' => 'email',
        'name' => 'nombre',
        'person_type' => 'tipo de persona',
        'first_name' => 'nombre',
        'last_name' => 'apellido',
        'company_name' => 'razón social',
        'document' => 'documento',
        'role' => 'cargo',
        'phone' => 'teléfono',
        'street' => 'calle',
        'street_number' => 'número',
        'city' => 'localidad',
        'province_id' => 'provincia',
        'postal_code' => 'código postal',
    ],
];
