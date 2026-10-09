<?php

namespace App\Actions;

use App\Exceptions\BudgetPdfGenerationException;
use App\Models\Budget;
use App\Support\Pdf\PdfRenderer;
use Throwable;

class GenerateBudgetPdfAction
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    /**
     * Returns the bytes of the budget's PDF (RF-61). Any failure of the render is reported as
     * a BudgetPdfGenerationException, so the caller shows an error and downloads nothing
     * (RF-67).
     *
     * @throws BudgetPdfGenerationException
     */
    public function handle(Budget $budget): string
    {
        try {
            return $this->renderer->render($this->renderHtml($budget));
        } catch (Throwable $exception) {
            report($exception);

            throw new BudgetPdfGenerationException($exception);
        }
    }

    /**
     * The document as HTML: header, client, current items and amounts. Removed items and
     * history entries are not part of it (RF-66).
     */
    public function renderHtml(Budget $budget): string
    {
        $budget->loadMissing(['client.province', 'items']);

        return view('pdf.budget', [
            'budget' => $budget,
            'client' => $budget->client,
            'items' => $budget->items,
            'phone' => $budget->client->contactPhone(),
        ])->render();
    }
}
