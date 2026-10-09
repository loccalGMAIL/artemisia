<?php

namespace App\Support\Pdf;

/**
 * Turns an HTML document into PDF bytes. It is an interface so the library behind it can be
 * replaced, and so a failed render can be simulated in tests (RF-67).
 */
interface PdfRenderer
{
    public function render(string $html): string;
}
