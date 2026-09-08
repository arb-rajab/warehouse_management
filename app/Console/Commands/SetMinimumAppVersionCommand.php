<?php

namespace App\Console\Commands;

use App\Models\MobileAppVersionRequirement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:set-minimum-app-version {version : The minimum supported mobile app version, e.g. 1.4.0}')]
#[Description('Reject mobile API requests from any app build older than this version')]
class SetMinimumAppVersionCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $version = (string) $this->argument('version');

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            $this->components->error('Version must be in the form MAJOR.MINOR.PATCH, e.g. 1.4.0.');

            return self::FAILURE;
        }

        // firstOrNew() rather than updateOrCreate(['id' => 1], ...): the latter
        // mass-assigns the primary key on the create path, which the id was
        // never fillable for — Eloquent used to drop it silently, so the row
        // got an auto-increment id anyway. The table holds one row either way.
        $requirement = MobileAppVersionRequirement::query()->firstOrNew();

        $requirement->minimum_version = $version;
        $requirement->save();

        $this->components->info("Minimum app version set to {$version}. Older clients will now be rejected.");

        return self::SUCCESS;
    }
}
