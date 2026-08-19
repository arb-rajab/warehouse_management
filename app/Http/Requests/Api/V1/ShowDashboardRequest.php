<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersDashboard;
use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardRequest extends FormRequest
{
    use FiltersDashboard;
}
