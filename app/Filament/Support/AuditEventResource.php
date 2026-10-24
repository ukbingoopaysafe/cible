<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<AuditEvent> */
abstract class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    abstract public static function department(): Department;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()
            ->with('actor')
            ->where('department', static::department()->value)
            ->where(function (Builder $query): void {
                $query->where('event_key', 'like', 'broker.%')
                    ->orWhere('event_key', 'like', 'request.reassignment.%');
            })
            ->when(
                ! $actor instanceof User,
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->when(
                $actor instanceof User && ! $actor->canManageDepartment(static::department()),
                fn (Builder $query): Builder => $query->where('actor_user_id', $actor->id),
            );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canWorkInDepartment(static::department()) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('التوقيت')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('actor.name')->label('المنفذ')->searchable(),
                TextColumn::make('event_key')->label('العملية')->badge()->formatStateUsing(fn (string $state): string => self::eventLabel($state)),
                TextColumn::make('subject_id')->label('السجل')->formatStateUsing(fn (int|string $state, AuditEvent $record): string => self::subjectLabel($record) . " #{$state}"),
                TextColumn::make('before_values')->label('قبل')->formatStateUsing(fn ($state): string => self::formatValues($state))->wrap()->toggleable(),
                TextColumn::make('after_values')->label('بعد')->formatStateUsing(fn ($state): string => self::formatValues($state))->wrap()->toggleable(),
                TextColumn::make('reason')->label('السبب')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_key')->label('العملية')->options([
                    'broker.created' => 'إضافة وسيط',
                    'broker.updated' => 'تعديل وسيط',
                    'broker.deleted' => 'حذف وسيط',
                    'request.reassignment.requested' => 'طلب إعادة تعيين',
                    'request.reassignment.approved' => 'الموافقة على إعادة التعيين',
                    'request.reassignment.rejected' => 'رفض إعادة التعيين',
                    'request.reassignment.direct' => 'إعادة تعيين مباشرة',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function eventLabel(string $eventKey): string
    {
        return match ($eventKey) {
            'broker.created' => 'إضافة وسيط',
            'broker.updated' => 'تعديل وسيط',
            'broker.deleted' => 'حذف وسيط',
            'request.reassignment.requested' => 'طلب إعادة تعيين',
            'request.reassignment.approved' => 'الموافقة على إعادة التعيين',
            'request.reassignment.rejected' => 'رفض إعادة التعيين',
            'request.reassignment.direct' => 'إعادة تعيين مباشرة',
            default => $eventKey,
        };
    }

    private static function subjectLabel(AuditEvent $record): string
    {
        return str_contains($record->subject_type, 'Broker') ? 'وسيط' : 'طلب';
    }

    /** @param mixed $values */
    private static function formatValues(mixed $values): string
    {
        if (! is_array($values) || $values === []) {
            return '—';
        }

        $labels = [
            'name' => 'الاسم',
            'phone_e164' => 'الهاتف',
            'phone_country' => 'رمز الدولة',
            'owner_user_id' => 'معرّف المسؤول',
            'target_user_id' => 'معرّف المسؤول المستهدف',
            'requested_by' => 'معرّف مقدم الطلب',
            'decided_by' => 'معرّف متخذ القرار',
            'status' => 'الحالة',
            'reassignment_id' => 'معرّف طلب إعادة التعيين',
        ];

        return collect($values)
            ->map(fn (mixed $value, string $key): string => ($labels[$key] ?? $key) . ': ' . self::formatValue($key, $value))
            ->implode('، ');
    }

    private static function formatValue(string $key, mixed $value): string
    {
        if ($key === 'status') {
            return match ($value) {
                'pending' => 'بانتظار المدير',
                'approved' => 'تمت الموافقة',
                'rejected' => 'مرفوض',
                default => (string) $value,
            };
        }

        return is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
