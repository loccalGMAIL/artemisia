<?php

use App\Support\BrandPalette;
use Filament\Facades\Filament;

beforeEach(function () {
    app()->setLocale('es');
});

it('la paleta de la marca usa los colores de la agencia', function () {
    expect(BrandPalette::RED)->toBe('#f4193e')
        ->and(BrandPalette::PINK)->toBe('#ffb1bb')
        ->and(BrandPalette::PURPLE)->toBe('#2c0735')
        ->and(BrandPalette::CREAM)->toBe('#fff7e9')
        ->and(BrandPalette::YELLOW)->toBe('#ffedbb');
});

it('el color principal es el rojo de la marca y el rosa es su tono claro', function () {
    $primary = BrandPalette::primary();

    expect($primary[500])->toBe(BrandPalette::RED)
        ->and($primary[200])->toBe(BrandPalette::PINK)
        ->and(array_keys($primary))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
});

it('los grises van del crema de la marca al morado, para que fondos, bordes y textos salgan de la paleta', function () {
    $gray = BrandPalette::gray();

    expect($gray[50])->toBe(BrandPalette::CREAM)
        ->and($gray[950])->toBe(BrandPalette::PURPLE)
        ->and(array_keys($gray))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
});

it('el amarillo de la marca es el tono claro de las advertencias', function () {
    expect(BrandPalette::warning()[100])->toBe(BrandPalette::YELLOW);
});

it('los dos paneles usan la misma paleta', function (string $panel) {
    $colors = Filament::getPanel($panel)->getColors();

    expect($colors)->toHaveKeys(['primary', 'gray', 'warning'])
        ->and($colors['primary'])->toBe(BrandPalette::primary())
        ->and($colors['gray'])->toBe(BrandPalette::gray())
        ->and($colors['warning'])->toBe(BrandPalette::warning());
})->with(['staff', 'client']);

it('los archivos de la identidad existen y son del tipo que dicen ser', function () {
    expect(file_exists(public_path('brand/logotipo.png')))->toBeTrue()
        ->and(file_exists(public_path('brand/isotipo.png')))->toBeTrue()
        ->and(file_exists(public_path('brand/favicon.ico')))->toBeTrue()
        ->and(file_exists(public_path('fonts/brand/AcuminVariableConcept.otf')))->toBeTrue()
        ->and(file_exists(public_path('css/brand/brand.css')))->toBeTrue();

    expect(file_get_contents(public_path('brand/logotipo.png'), false, null, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(file_get_contents(public_path('brand/isotipo.png'), false, null, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(file_get_contents(public_path('brand/favicon.ico'), false, null, 0, 4))->toBe("\x00\x00\x01\x00");
});

it('la hoja de estilos de la marca declara la tipografía y los colores', function () {
    $css = file_get_contents(public_path('css/brand/brand.css'));

    expect($css)->toContain('@font-face')
        ->toContain('Acumin Variable Concept')
        ->toContain('AcuminVariableConcept.otf')
        ->toContain(BrandPalette::PURPLE)
        ->toContain(BrandPalette::CREAM)
        ->toContain(BrandPalette::RED);
});

it('la página de ingreso de cada portal muestra la marca de Artemisia, no la de Laravel', function (string $url) {
    $response = $this->get($url)->assertOk();

    $response->assertSee('Artemisia')
        ->assertSee('brand/logotipo.png', false)
        ->assertSee('brand/favicon.ico', false)
        ->assertSee('css/brand/brand.css', false)
        ->assertSee('Acumin Variable Concept', false)
        ->assertDontSee('Laravel');
})->with(['/staff/login', '/portal/login']);

it('el nombre de la marca sale de los textos de la aplicación', function () {
    expect(__('brand.name'))->toBe('Artemisia');
});
