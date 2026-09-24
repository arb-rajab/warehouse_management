<?php

namespace App\Http\Requests\Trips;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CheckpointStatus;
use Illuminate\Validation\Rules\Enum;

class UpdateCheckpointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'checkpoint_id' => 'required|exists:checkpoints,id',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'cmr_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:51200',
            'status' => ['required', Rule::in(array_column(CheckpointStatus::cases(), 'value'))],
            'notes' => 'nullable|string|max:65535', // text col
            'longitudes' => 'nullable|numeric|between:-180,180',
            'latitudes' => 'nullable|numeric|between:-90,90'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $statusEnumValues = implode(', ', array_column(CheckpointStatus::cases(), 'value'));
        return [
            'checkpoint_id.required' => 'Checkpoint ID is required.',
            'checkpoint_id.exists' => 'The selected checkpoint does not exist.',
            'photos.array' => 'Photos must be an array.',
            'photos.*.image' => 'Each photo must be an image file.',
            'photos.*.mimes' => 'Each photo must be one of the following types: jpeg, png, jpg, gif, svg.',
            'photos.*.max' => 'Each photo may not be larger than 2MB.',
            'cmr_file.file' => 'The CMR file must be a valid file.',
            'cmr_file.mimes' => 'The CMR file must be a PDF, DOC, or DOCX file.',
            'cmr_file.max' => 'The CMR file may not be larger than 2MB.',
            'status.required' => 'Status is required.',
            'status.in' => 'The status must be one of the following values: ' . $statusEnumValues . '.',
            'longitudes.numeric' => 'The longitudes must be a valid number.',
            'longitudes.between' => 'The longitudes must be between -180 and 180 degrees.',
            'latitudes.numeric' => 'The latitudes must be a valid number.',
            'latitudes.between' => 'The latitudes must be between -90 and 90 degrees.'
        ];
    }
}
