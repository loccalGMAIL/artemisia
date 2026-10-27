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
        'edit' => 'Editar',
        'change_role' => 'Cambiar rol',
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
    ],
    'notifications' => [
        'created' => 'Cuenta creada. Se envió el enlace para definir la contraseña.',
        'updated' => 'Cuenta actualizada.',
        'profile_saved' => 'Perfil actualizado.',
        'role_changed' => 'Rol actualizado.',
        'deactivated' => 'Cuenta desactivada.',
        'activated' => 'Cuenta activada.',
    ],
    'profile' => [
        'email_hint' => 'El email lo cambia un administrador.',
        'current_password' => 'Contraseña actual',
        'new_password' => 'Contraseña nueva',
        'new_password_confirmation' => 'Repetir la contraseña nueva',
        'no_password' => 'Su cuenta todavía no tiene contraseña. Para definirla, use la recuperación de contraseña de la pantalla de acceso.',
    ],
    'email_changed' => [
        'subject' => 'El email de su cuenta en :app cambió',
        'greeting' => 'Hola :name,',
        'old_address' => 'El email de su cuenta en :app cambió a :email. Esta dirección ya no identifica a la cuenta.',
        'new_address' => 'Esta dirección es ahora el email de su cuenta en :app. Antes era :email.',
        'unexpected' => 'Si no esperaba este cambio, comuníquese con la agencia.',
    ],
];
