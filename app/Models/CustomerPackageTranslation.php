<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'lang', 'customer_package_id'])]
class CustomerPackageTranslation extends Model
{
    /** @return BelongsTo<CustomerPackage, $this> */
    public function customer_package(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }
}
