<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Foundation\Http\FormRequest;

class EmptyPalletRequest extends FormRequest
{
    use ValidatesOptionalNote;
}
