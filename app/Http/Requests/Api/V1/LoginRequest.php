<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesLoginCredentials;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use ValidatesLoginCredentials;
}
