<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersDashboard;
use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardRequest extends FormRequest
{
    use FiltersDashboard;
}
