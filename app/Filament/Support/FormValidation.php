<?php

namespace App\Filament\Support;

use Illuminate\Validation\ValidationException;

/**
 * The Actions throw validation errors keyed by plain field names; a Filament form expects
 * them under its state path. This only renames the keys, it adds no rules (constitution,
 * principle 3).
 */
final class FormValidation
{
    public static function prefixed(ValidationException $exception, string $statePath): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $field => $errors) {
            $messages["{$statePath}.{$field}"] = $errors;
        }

        return ValidationException::withMessages($messages);
    }

    /** State path of the form of the action currently mounted at the given nesting index. */
    public static function actionPath(int $nestingIndex = 0): string
    {
        return "mountedActions.{$nestingIndex}.data";
    }
}
