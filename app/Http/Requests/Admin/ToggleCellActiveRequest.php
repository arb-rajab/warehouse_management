<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Foundation\Http\FormRequest;

class ToggleCellActiveRequest extends FormRequest
{
    use ValidatesOptionalNote;
}
