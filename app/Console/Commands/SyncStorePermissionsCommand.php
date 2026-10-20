<?php

namespace App\Console\Commands;

use App\Support\StorePermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('store:sync-permissions {--dry-run : List the permissions that would be created without writing anything}')]
#[Description('Create the store permissions the back office enforces that are missing; never changes existing rows or role grants')]
class SyncStorePermissionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = StorePermissions::sync($dryRun);

        foreach ($result['created'] as $name) {
            $this->line(($dryRun ? 'Would create ' : 'Created ').$name.' ('.StorePermissions::all()[$name].')');
        }

        $this->components->info(sprintf(
            $dryRun
                ? 'Dry run, nothing written: %d would be created, %d already exist (%d store permissions defined in code).'
                : '%d created, %d already existed (%d store permissions defined in code).',
            count($result['created']),
            count($result['existing']),
            count(StorePermissions::all()),
        ));

        return self::SUCCESS;
    }
}
