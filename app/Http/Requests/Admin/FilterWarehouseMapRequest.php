<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The warehouse map page and its rows payload: an optional `warehouse_ids[]`;
 * omitting it draws every warehouse's rows.
 */
class FilterWarehouseMapRequest extends FormRequest
{
    use FiltersByWarehouseIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->warehouseIdsFilterRules();
    }
}
