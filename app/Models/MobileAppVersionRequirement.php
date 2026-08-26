<?php

namespace App\Models;

use Database\Factories\MobileAppVersionRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $minimum_version
 */
#[Fillable(['minimum_version'])]
class MobileAppVersionRequirement extends Model
{
    /** @use HasFactory<MobileAppVersionRequirementFactory> */
    use HasFactory;

    /**
     * The command always upserts row id 1, so there is at most one
     * requirement stored — null means no minimum has been configured yet,
     * which callers treat as "don't restrict anything".
     */
    public static function minimumVersion(): ?string
    {
        return static::query()->value('minimum_version');
    }
}
