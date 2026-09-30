<?php

namespace App\Models;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomersImport implements ToModel, WithHeadingRow
{
    public function model(array $row): Customer
    {
        return new Customer([
           'user_id'     => $row['user_id'],
        ]);
    }
}
