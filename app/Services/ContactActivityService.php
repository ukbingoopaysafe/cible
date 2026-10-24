<?php

namespace App\Services;

use App\Enums\ContactActivityChannel;
use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\RentalActivity;
use App\Models\RentalRequest;
use App\Models\ResaleActivity;
use App\Models\ResaleRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactActivityService
{
    public function record(User $actor, Department $department, int $requestId, array $data): Model
    {
        $this->authorize($actor, $department);
        $validated = Validator::make($data, [
            'channel' => ['required', Rule::enum(ContactActivityChannel::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'result' => ['required', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'next_action' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
        $request = $requestClass::query()->with(['customer', 'broker'])->findOrFail($requestId);
        if ($request->status === RequestStatus::Closed) {
            throw ValidationException::withMessages(['request' => 'لا يمكن تسجيل تواصل جديد على طلب مغلق؛ يُسجل تواصل ما بعد الصفقة في مساره المستقل.']);
        }
        $contactParty = $request->contact_party;
        $contact = match ($contactParty) {
            ContactParty::Customer => $request->customer,
            ContactParty::Broker => $request->broker,
            default => null,
        };

        if (! $contact || blank($contact->name)) {
            throw ValidationException::withMessages(['request' => 'تعذر تحديد جهة الاتصال الحالية للطلب.']);
        }

        return DB::transaction(fn (): Model => $request->activities()->create([
            'actor_user_id' => $actor->getKey(),
            'contact_party' => $contactParty,
            'contact_name_snapshot' => $contact->name,
            'channel' => $validated['channel'],
            'occurred_at' => $validated['occurred_at'],
            'result' => trim($validated['result']),
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'next_action' => filled($validated['next_action'] ?? null) ? trim($validated['next_action']) : null,
        ]));
    }

    /** @return Collection<int, RentalActivity|ResaleActivity> */
    public function history(User $actor, Department $department, int $requestId): Collection
    {
        $this->authorize($actor, $department);
        $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
        $request = $requestClass::query()->findOrFail($requestId);

        return $request->activities()->with('actor')->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    private function authorize(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException;
        }
    }
}
