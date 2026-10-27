<?php

use App\Enums\AccountHistoryField;

beforeEach(function () {
    app()->setLocale('es');
});

it('RF-20, RF-21, RF-22, RF-24: el historial de cuentas reconoce y etiqueta los cambios de nombre, email y contraseña', function (string $value, string $label) {
    $field = AccountHistoryField::tryFrom($value);

    expect($field)->not->toBeNull()
        ->and($field->label())->toBe($label);
})->with([
    'nombre' => ['name_changed', 'Cambio de nombre'],
    'email' => ['email_changed', 'Cambio de email'],
    'contraseña' => ['password_changed', 'Cambio de contraseña'],
]);
