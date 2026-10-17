<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A checkpoint's notes, saved from the trip detail on change: the same rule
 * as the trip form's checkpoint notes.
 */
class UpdateCheckpointNotesRequest extends FormRequest
{
    use ValidatesStoreTrip;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['notes' => $this->checkpointRules()['checkpoints_array.*.checkpoint_notes']];
    }
}
