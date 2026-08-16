<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use Illuminate\Foundation\Http\FormRequest;

class ShowRowsFullRequest extends FormRequest
{
    use FiltersByStaleAfterDays;
}
