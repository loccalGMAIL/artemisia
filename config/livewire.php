<?php

use App\Actions\SendPieceForClientApprovalAction;

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Only the upload rules differ from the package defaults: a piece submission may be up
    | to 100 MB (spec 004, RNF-1), while Livewire stops temporary uploads at 12 MB. Livewire
    | merges this file with its own by top-level key, so the whole section is kept here.
    | The web server must allow the same size (upload_max_filesize and post_max_size).
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:'.SendPieceForClientApprovalAction::MAX_SIZE_KB],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
