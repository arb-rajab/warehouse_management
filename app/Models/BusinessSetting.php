<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    /**
     * The currency settings used while their `business_settings` row is
     * missing or blank. The back office Currency page shows these, and
     * format_price() formats with them, so the two cannot disagree: dev once
     * had no rows, showed "2 decimals" in the panel and printed "11€".
     */
    public const array CURRENCY_DEFAULTS = ['symbol_format' => 1, 'decimal_separator' => 1, 'no_of_decimals' => 2];
}
