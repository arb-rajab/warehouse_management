<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('store:move-trade-licenses {--dry-run : List what would be moved without moving it}')]
#[Description('Move trade licences out of the web root (public/uploads/licenses) onto the private disk')]
class MoveLegacyTradeLicensesCommand extends Command
{
    private const DIRECTORY = 'uploads/licenses';

    /**
     * The legacy store saved licences under public/uploads/licenses, where anyone
     * who guesses a filename can fetch one. They are served through a controller
     * from the private disk now (TradeLicenseStorage), under the same names.
     * Each file is copied, checked by size, and only then removed from the web
     * root; a name that already exists on the private disk is left where it is
     * and reported, never overwritten.
     */
    public function handle(): int
    {
        $source = public_path(self::DIRECTORY);

        if (! is_dir($source)) {
            $this->components->info("Nothing to move: {$source} does not exist.");

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $conflicts = [];

        foreach (glob($source.'/*') ?: [] as $path) {
            if (! is_file($path) || is_link($path)) {
                continue;
            }

            $name = basename($path);
            $relative = self::DIRECTORY.'/'.$name;

            if ($disk->exists($relative)) {
                $conflicts[] = $name;

                continue;
            }

            if (! $dryRun) {
                $disk->put($relative, (string) file_get_contents($path));

                if ($disk->size($relative) !== filesize($path)) {
                    $disk->delete($relative);
                    $this->components->error("Copy of {$name} did not match; left in place.");

                    return self::FAILURE;
                }

                unlink($path);
            }

            $moved++;
        }

        $verb = $dryRun ? 'Would move' : 'Moved';
        $this->components->info("{$verb} {$moved} trade licence file(s) off the web root.");

        foreach ($conflicts as $name) {
            $this->components->warn("{$name} already exists on the private disk; left in the web root. Compare and remove it by hand.");
        }

        return $conflicts === [] ? self::SUCCESS : self::FAILURE;
    }
}
