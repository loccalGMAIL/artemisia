<?php

use App\Actions\SendPieceForClientApprovalAction;

it('RNF-1, RF-29: la subida temporal de Livewire admite archivos de hasta 100 MB, no el tope por defecto de 12 MB', function () {
    $rules = config('livewire.temporary_file_upload.rules');

    expect($rules)->toBeArray()
        ->and($rules)->toContain('max:'.SendPieceForClientApprovalAction::MAX_SIZE_KB)
        ->and(SendPieceForClientApprovalAction::MAX_SIZE_KB)->toBe(100 * 1024);
});
