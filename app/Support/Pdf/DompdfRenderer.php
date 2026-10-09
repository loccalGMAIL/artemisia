<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF rendering with barryvdh/laravel-dompdf (plan D-7). Remote resources stay disabled, so a
 * document can only use what the application itself puts in the HTML.
 */
class DompdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        return Pdf::setOption(['isRemoteEnabled' => false])
            ->loadHTML($html)
            ->setPaper('a4')
            ->output();
    }
}
