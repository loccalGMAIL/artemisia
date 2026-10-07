<?php

return [
    'model' => 'cuenta',
    'plural' => 'Cuentas',
    'roles' => [
        'admin' => 'Administrador',
        'staff' => 'Equipo',
        'client' => 'Cliente',
    ],
    'fields' => [
        'name' => 'Nombre completo',
        'email' => 'Email',
        'role' => 'Rol',
        'is_active' => 'Activa',
        'created_at' => 'Creada',
    ],
    'actions' => [
        'change_role' => 'Cambiar rol',
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
    ],
    'notifications' => [
        'created' => 'Cuenta creada. Se envió el enlace para definir la contraseña.',
        'role_changed' => 'Rol actualizado.',
        'deactivated' => 'Cuenta desactivada.',
        'activated' => 'Cuenta activada.',
    ],
];
