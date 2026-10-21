<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

        /**
         * The countries a customer may choose (`status` 1).
         *
         * @param  Builder<self>  $query
         */
        #[Scope]
        protected function active(Builder $query): void
        {
            $query->where('status', 1);
        }

}
