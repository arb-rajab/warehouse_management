<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersCellStatusLogs;
}
