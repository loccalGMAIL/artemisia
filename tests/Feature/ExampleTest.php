<?php

it('responde correctamente en la raíz', function () {
    $this->get('/')->assertOk();
});
