<?php

namespace App\Models\Concerns;

use App\Models\ContactPhoneNumber;
use App\Models\ContactChangeRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasContactPhoneNumbers
{
    public static function bootHasContactPhoneNumbers(): void
    {
        static::deleted(function (Model $contact): void {
            if (! method_exists($contact, 'isForceDeleting') || $contact->isForceDeleting()) {
                $contact->phoneNumbers()->delete();
            }
        });
    }

    public function phoneNumbers(): MorphMany
    {
        return $this->morphMany(ContactPhoneNumber::class, 'phoneable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function contactChangeRequests(): MorphMany
    {
        return $this->morphMany(ContactChangeRequest::class, 'subject');
    }

    public function primaryPhoneNumber(): MorphOne
    {
        return $this->morphOne(ContactPhoneNumber::class, 'phoneable')
            ->where('is_primary', true);
    }

    public function primaryPhone(): ?ContactPhoneNumber
    {
        if ($this->relationLoaded('phoneNumbers')) {
            return $this->getRelation('phoneNumbers')->first(fn (ContactPhoneNumber $phone): bool => $phone->is_primary);
        }

        return $this->primaryPhoneNumber()->first();
    }
}
