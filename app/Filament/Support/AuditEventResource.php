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
                    'rental_area.created' => 'إضافة منطقة إيجار',
                    'resale_area.created' => 'إضافة منطقة إعادة بيع',
                    'close_reason.created' => 'إضافة تصنيف إغلاق',
                    'close_reason.activation_changed' => 'تغيير إتاحة تصنيف الإغلاق',
                    'close_reason.renamed' => 'تعديل تصنيف إغلاق',
                    'close_reason.deleted' => 'حذف تصنيف إغلاق',
                    'customer.created' => 'إضافة عميل',
                    'customer.updated' => 'تعديل عميل',
                    'request.created' => 'إنشاء طلب',
                    'request.details_updated' => 'تعديل طلب',
                    'request.customer_linked' => 'ربط عميل بالطلب',
                    'request.stage_changed' => 'تغيير مرحلة طلب',
                    'request.status_changed' => 'تغيير حالة طلب',
                    'request.follow_up_changed' => 'تغيير موعد متابعة',
                    'contact.activity_recorded' => 'تسجيل تواصل على طلب',
                    'request.reassignment.requested' => 'طلب إعادة تعيين',
                    'request.reassignment.approved' => 'الموافقة على إعادة التعيين',
                    'request.reassignment.rejected' => 'رفض إعادة التعيين',
                    'request.reassignment.cancelled' => 'إلغاء طلب إعادة التعيين',
                    'request.reassignment.direct' => 'إعادة تعيين مباشرة',
                    'lead_source.created' => 'إضافة مصدر وصول',
                    'lead_source.activation_changed' => 'تغيير إتاحة مصدر وصول',
                    'deal.created' => 'إنشاء صفقة',
                    'recommendation.created' => 'إضافة ترشيح',
                    'recommendation.status_changed' => 'تغيير حالة ترشيح',
                    'field_inspection.recorded' => 'تسجيل فحص ميداني',
                    'viewing.scheduled' => 'تحديد معاينة',
                    'viewing.result_recorded' => 'تسجيل نتيجة معاينة',
                    'aftercare.started' => 'بدء ما بعد الصفقة',
                    'aftercare.activity.recorded' => 'تسجيل تواصل ما بعد الصفقة',
                    'staff.employee_created' => 'إضافة موظف',
                    'staff.employee_updated' => 'تعديل موظف',
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
            'rental_area.created' => 'إضافة منطقة إيجار',
            'resale_area.created' => 'إضافة منطقة إعادة بيع',
            'close_reason.created' => 'إضافة تصنيف إغلاق',
            'close_reason.activation_changed' => 'تغيير إتاحة تصنيف الإغلاق',
            'close_reason.renamed' => 'تعديل تصنيف إغلاق',
            'close_reason.deleted' => 'حذف تصنيف إغلاق',
            'customer.created' => 'إضافة عميل',
            'customer.updated' => 'تعديل عميل',
            'request.created' => 'إنشاء طلب',
            'request.details_updated' => 'تعديل طلب',
            'request.customer_linked' => 'ربط عميل بالطلب',
            'request.stage_changed' => 'تغيير مرحلة طلب',
            'request.status_changed' => 'تغيير حالة طلب',
            'request.follow_up_changed' => 'تغيير موعد متابعة',
            'contact.activity_recorded' => 'تسجيل تواصل على طلب',
            'request.reassignment.requested' => 'طلب إعادة تعيين',
            'request.reassignment.approved' => 'الموافقة على إعادة التعيين',
            'request.reassignment.rejected' => 'رفض إعادة التعيين',
            'request.reassignment.cancelled' => 'إلغاء طلب إعادة التعيين',
            'request.reassignment.direct' => 'إعادة تعيين مباشرة',
            'lead_source.created' => 'إضافة مصدر وصول',
            'lead_source.activation_changed' => 'تغيير إتاحة مصدر وصول',
            'deal.created' => 'إنشاء صفقة',
            'recommendation.created' => 'إضافة ترشيح',
            'recommendation.status_changed' => 'تغيير حالة ترشيح',
            'field_inspection.recorded' => 'تسجيل فحص ميداني',
            'viewing.scheduled' => 'تحديد معاينة',
            'viewing.result_recorded' => 'تسجيل نتيجة معاينة',
            'aftercare.started' => 'بدء ما بعد الصفقة',
            'aftercare.activity.recorded' => 'تسجيل تواصل ما بعد الصفقة',
            'staff.employee_created' => 'إضافة موظف',
            'staff.employee_updated' => 'تعديل موظف',
            default => $eventKey,
        };
    }

    private static function subjectLabel(AuditEvent $record): string
    {
        return match (true) {
            str_contains($record->subject_type, 'Broker') => 'وسيط',
            str_contains($record->subject_type, 'Customer') => 'عميل',
            str_contains($record->subject_type, 'Request') => 'طلب',
            str_contains($record->subject_type, 'Deal') => 'صفقة',
            str_contains($record->subject_type, 'Aftercare') => 'ما بعد الصفقة',
            str_contains($record->subject_type, 'Recommendation') => 'ترشيح',
            str_contains($record->subject_type, 'FieldInspection') => 'فحص ميداني',
            str_contains($record->subject_type, 'Viewing') => 'معاينة',
            str_contains($record->subject_type, 'LeadSource') => 'مصدر وصول',
            str_contains($record->subject_type, 'User') => 'موظف',
            default => 'سجل',
        };
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
            'customer_id' => 'معرّف العميل',
            'broker_id' => 'معرّف الوسيط',
            'stage' => 'المرحلة',
            'outcome' => 'النتيجة',
            'next_follow_up_at' => 'موعد المتابعة',
            'is_active' => 'مفعل',
            'department_access' => 'صلاحية القسم',
            'email_changed' => 'تغيير البريد الإلكتروني',
            'email_set' => 'تسجيل البريد الإلكتروني',
            'nationality' => 'الجنسية',
            'from' => 'من',
            'to' => 'إلى',
            'result' => 'النتيجة',
            'channel' => 'قناة التواصل',
            'recommendation_id' => 'معرّف الترشيح',
            'request_id' => 'معرّف الطلب',
            'activity_id' => 'معرّف سجل التواصل',
            'currency' => 'العملة',
            'agreed_total_value' => 'قيمة الصفقة الإجمالية',
            'password_changed' => 'تغيير كلمة المرور',
            'photo_match' => 'مطابقة الصور',
            'has_observed_defects' => 'تسجيل عيوب',
        ];

        return collect($values)
            ->map(fn (mixed $value, string $key): string => ($labels[$key] ?? $key) . ': ' . self::formatValue($key, $value))
            ->implode('، ');
    }

    private static function formatValue(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (in_array($key, ['status', 'from', 'to'], true)) {
            return match ($value) {
                'pending' => 'بانتظار المدير',
                'approved' => 'تمت الموافقة',
                'rejected' => 'مرفوض',
                'active' => 'نشط',
                'paused' => 'متوقف مؤقتًا',
                'closed' => 'مغلق',
                'proposed' => 'مقترحة',
                'shared' => 'أُرسلت للعميل',
                'selected' => 'اختارها العميل',
                'withdrawn' => 'سُحبت',
                default => (string) $value,
            };
        }
        if ($key === 'stage') {
            return match ($value) {
                'intake' => 'استقبال', 'qualification' => 'تأهيل', 'unit_search' => 'بحث عن وحدات',
                'recommendation' => 'ترشيح', 'viewing' => 'معاينة', 'negotiation' => 'تفاوض',
                default => (string) $value,
            };
        }
        if ($key === 'outcome') {
            return match ($value) { 'successful' => 'ناجح', 'unsuccessful' => 'غير ناجح', default => (string) $value };
        }
        if ($key === 'department') {
            return match ($value) { 'rental' => 'الإيجار', 'resale' => 'إعادة البيع', default => (string) $value };
        }
        if ($key === 'result') {
            return match ($value) {
                'interested' => 'مهتم', 'not_interested' => 'غير مهتم',
                'second_viewing' => 'معاينة أخرى', 'negotiating' => 'تفاوض',
                'cancelled' => 'أُلغيت', default => (string) $value,
            };
        }

        return is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
