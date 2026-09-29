<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Country extends Model
{
        /**
         * Get the Zone that owns the Country
         *
         * @return BelongsTo
         */
        public function zone()
        {
            return $this->belongsTo(Zone::class);
        }

}
