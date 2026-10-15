<?php

namespace App\Http\Requests\Concerns;

use App\Enums\StoreTripList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The `list` parameter every trip row action sends: the screen it was fired
 * from, which is where it returns.
 */
trait ValidatesStoreTripList
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function tripListRules(): array
    {
        return ['list' => ['required', 'string', Rule::enum(StoreTripList::class)]];
    }

    public function tripList(): StoreTripList
    {
        return StoreTripList::from($this->string('list')->toString());
    }
}
