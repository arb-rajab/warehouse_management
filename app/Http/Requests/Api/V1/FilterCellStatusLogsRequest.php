<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersCellStatusLogs;
}
