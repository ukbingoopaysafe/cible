<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

final class FilamentValidationHandler
{
    /**
     * Executes a callback and maps any ValidationException to schema component state paths,
     * while also displaying a prominent danger notification so the user sees the error immediately.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback, ?Schema $schema = null, string $errorTitle = 'تعذر الحفظ')
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            $firstMessage = collect($exception->errors())->flatten()->first() ?: 'يرجى مراجعة البيانات المدخلة وتصحيح الأخطاء.';

            Notification::make()
                ->title($errorTitle)
                ->body($firstMessage)
                ->danger()
                ->send();

            if (! $schema) {
                throw $exception;
            }

            $mapped = [];
            foreach ($exception->errors() as $key => $messages) {
                $component = $schema->getComponent($key);
                $statePath = $component ? $component->getStatePath() : $key;
                $mapped[$statePath] = $messages;
            }

            throw ValidationException::withMessages($mapped);
        }
    }
}
