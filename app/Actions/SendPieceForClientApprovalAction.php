<?php

namespace App\Actions;

use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendPieceForClientApprovalAction extends ChangesPieceStatus
{
    /** Formats a submission admits (RF-29). */
    public const ALLOWED_EXTENSIONS = ['jpg', 'png', 'pdf', 'mp4'];

    /** Biggest file a submission admits, in kilobytes: 100 MB (RNF-1). */
    public const MAX_SIZE_KB = 102400;

    /**
     * Sends a piece in review to the client with a single file. Every submission is kept with
     * its file and date, however many the piece receives (RF-22, RF-23, RF-28 to RF-31).
     *
     * @throws InvalidPieceTransitionException
     * @throws ValidationException
     */
    public function handle(Piece $piece, ?UploadedFile $file, User $actor): PieceApprovalSubmission
    {
        $this->assertMoveAllowed($piece, PieceStatus::ClientApproval);

        $this->validateFile($file);

        $path = $file->store('piece-submissions/'.$piece->id);

        try {
            return DB::transaction(function () use ($piece, $file, $path, $actor): PieceApprovalSubmission {
                $submission = $piece->submissions()->create([
                    'file_path' => $path,
                    'file_extension' => $this->extensionOf($file),
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);

                $this->moveTo($piece, PieceStatus::ClientApproval, [], $actor);

                return $submission;
            });
        } catch (Throwable $exception) {
            Storage::delete($path);

            throw $exception;
        }
    }

    protected function allowedFrom(): array
    {
        return [PieceStatus::InReview];
    }

    /**
     * @throws ValidationException
     */
    private function validateFile(?UploadedFile $file): void
    {
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS), 'max:'.self::MAX_SIZE_KB],
        ], [
            'file.required' => __('pieces.validation.file_required'),
            'file.file' => __('pieces.validation.file_required'),
            'file.mimes' => __('pieces.validation.file_format'),
            'file.max' => __('pieces.validation.file_size'),
        ])->validate();
    }

    private function extensionOf(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }
}
