<?php

return [
    'navigation_group' => 'Registros',
    'prune' => [
        'done' => 'Se eliminaron :count registros de acceso con más de :months meses.',
    ],
    'access_log' => [
        'model' => 'registro de acceso',
        'plural' => 'Registros de acceso',
    ],
    'account_history' => [
        'model' => 'asiento de cuenta',
        'plural' => 'Historial de cuentas',
    ],
    'fields' => [
        'created_at' => 'Fecha y hora',
        'email_used' => 'Email usado',
        'user' => 'Cuenta',
        'portal' => 'Portal',
        'method' => 'Método',
        'outcome' => 'Resultado',
        'rejection_reason' => 'Motivo del rechazo',
        'field' => 'Hecho',
        'old_value' => 'Valor anterior',
        'new_value' => 'Valor nuevo',
        'author' => 'Autor',
    ],
    'portals' => [
        'staff' => 'Equipo',
        'client' => 'Clientes',
    ],
    'methods' => [
        'password' => 'Email y contraseña',
        'google' => 'Google',
    ],
    'outcomes' => [
        'success' => 'Ingreso',
        'rejected' => 'Rechazado',
    ],
    'history_fields' => [
        'created' => 'Alta',
        'activated' => 'Activación',
        'deactivated' => 'Desactivación',
        'role_changed' => 'Cambio de rol',
    ],
];
