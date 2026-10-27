<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/** Parent context for the existing order/checkpoint URLs, which carry only a child ID. */
abstract class TripWriteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['trip_id' => ['required', 'integer', 'min:1']];
    }

    /** Call inside the write transaction, before locking its children. */
    public function lockedTrip(): Trip
    {
        $trip = Trip::query()->lockForUpdate()->findOrFail($this->integer('trip_id'));
        if ($trip->status === TripStatus::Closed->value) {
            throw ValidationException::withMessages(['trip_id' => __('messages.trip_closed_read_only')]);
        }

        return $trip;
    }
}
