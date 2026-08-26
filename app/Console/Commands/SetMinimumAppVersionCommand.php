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

        MobileAppVersionRequirement::query()->updateOrCreate(['id' => 1], [
            'minimum_version' => $version,
        ]);

        $this->components->info("Minimum app version set to {$version}. Older clients will now be rejected.");

        return self::SUCCESS;
    }
}
