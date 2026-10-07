<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The Actions throw validation errors keyed by plain field names; a Filament form expects
 * them under its state path. This only renames the keys and shows the errors that belong
 * to no field, it adds no rules (constitution, principle 3).
 */
final class FormValidation
{
    /** Errors about the whole operation rather than one field, shown as a notification. */
    private const NON_FIELD_KEYS = ['contacts', 'contact'];

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

    /**
     * Reports a failed Action inside a modal action: whole-operation errors go to a
     * notification and keep the modal open; field errors go to the form fields.
     *
     * @throws ValidationException
     */
    public static function forAction(ValidationException $exception, Action $action): never
    {
        $errors = $exception->errors();

        foreach (self::NON_FIELD_KEYS as $key) {
            if (isset($errors[$key])) {
                Notification::make()->danger()->title($errors[$key][0])->send();

                $action->halt();
            }
        }

        throw self::prefixed($exception, self::actionPath($action->getNestingIndex()));
    }
}
