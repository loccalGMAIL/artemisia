<?php

it('RNF-4: la sesión se cierra por inactividad a los 120 minutos', function () {
    expect(config('session.lifetime'))->toBe(120)
        ->and(config('session.expire_on_close'))->toBeFalse();
});

it('RNF-3: el enlace de definición de contraseña vence a las 24 horas', function () {
    expect(config('auth.passwords.users.expire'))->toBe(24 * 60);
});
