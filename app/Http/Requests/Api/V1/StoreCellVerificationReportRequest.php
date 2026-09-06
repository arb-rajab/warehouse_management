<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CellState;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Models\CellVerificationRound;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCellVerificationReportRequest extends FormRequest
{
    use ValidatesOptionalNote;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->noteRules(),
            'cell_verification_round_id' => ['required', 'integer', 'exists:cell_verification_rounds,id'],
            'cell_id' => ['required', 'integer', 'exists:cells,id'],
            'is_correct' => ['required', 'boolean'],
            // Required together whenever is_correct is false — what the user actually saw.
            'reported_cell_state' => ['required_if:is_correct,false', Rule::enum(CellState::class)],
            'reported_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'reported_boxes_count' => ['nullable', 'integer', 'min:0'],
            'reported_expiration_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A reported state of full/opened means a pallet was seen — its product
            // and box count are then required too, not just the cell state itself.
            $reportedState = $this->input('reported_cell_state');

            if (in_array($reportedState, [CellState::Full->value, CellState::Opened->value], true)) {
                if (! $this->filled('reported_product_id')) {
                    $validator->errors()->add('reported_product_id', __('validation.required_if', ['attribute' => 'reported product', 'other' => 'reported cell state', 'value' => $reportedState]));
                }

                if (! $this->filled('reported_boxes_count')) {
                    $validator->errors()->add('reported_boxes_count', __('validation.required_if', ['attribute' => 'reported boxes count', 'other' => 'reported cell state', 'value' => $reportedState]));
                }
            }

            if ($this->filled('cell_verification_round_id')) {
                $round = CellVerificationRound::query()->find($this->integer('cell_verification_round_id'));

                if ($round !== null && Gate::forUser($this->user())->denies('view', $round)) {
                    $validator->errors()->add('cell_verification_round_id', 'This verification round belongs to another user.');
                }
            }
        });
    }
}
