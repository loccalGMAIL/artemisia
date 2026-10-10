<?php

namespace App\Support;

use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;

/**
 * The identity of Artemisia applied to a panel, so the staff and the client portals look like
 * one product: name, logo, favicon, palette and typeface. The look that the color scales cannot
 * reach (the purple menu, the login screen) lives in public/css/brand/brand.css.
 */
final class BrandPanel
{
    public static function configure(Panel $panel): Panel
    {
        return $panel
            ->brandName(fn (): string => __('brand.name'))
            ->brandLogo(fn (): string => asset('brand/isotipo.png'))
            ->brandLogoHeight('4.5rem')
            ->favicon(fn (): string => asset('brand/favicon.ico'))
            ->colors(BrandPalette::colors())
            ->font('Acumin Variable Concept', fn (): string => asset('css/brand/brand.css'), LocalFontProvider::class);
    }
}
