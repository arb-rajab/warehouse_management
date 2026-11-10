<?php

namespace App\Models;

use Database\Factories\ManualPaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualPaymentMethod extends Model
{
    /** @use HasFactory<ManualPaymentMethodFactory> */
    use HasFactory;

    protected $guarded = [];
}
