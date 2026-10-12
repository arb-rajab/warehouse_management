<?php

namespace App\Console\Commands;

use App\Models\StoreUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class fixMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:members {--dry-run : Report what would change without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync member serials from the otajer API and retire duplicate accounts that share an AccSysID';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        try {
            $endpoint = config('services.otajer.url').'/api/rest/loadmembers/'.config('services.otajer.token');
            $allMembers = Http::timeout(30)->throw()->get($endpoint)->json();

            $retiredCount = 0;

            DB::transaction(function () use ($allMembers, $isDryRun, &$retiredCount): void {
                foreach ($allMembers['Members'] as $member) {
                    if ($member['AccSystemID'] == null) {
                        continue;
                    }

                    if (! $isDryRun) {
                        StoreUser::where('AccSysID', $member['AccSystemID'])->update([
                            'member_serial' => $member['Serial'],
                        ]);
                    }

                    $users = StoreUser::where('AccSysID', $member['AccSystemID'])->get();

                    if ($users->count() <= 1) {
                        continue;
                    }

                    $duplicate = $users->where('profits', '=', 0)->first();

                    if (! $duplicate) {
                        continue;
                    }

                    $retiredCount++;
                    $this->line(($isDryRun ? '[dry-run] would retire' : 'Retiring').' duplicate user #'.$duplicate->id.' (AccSysID '.$member['AccSystemID'].')');

                    if (! $isDryRun) {
                        // Soft delete: the users table has no deleted_at, so the duplicate is
                        // banned and detached from the AccSysID (which also keeps the next
                        // run from picking it up again) instead of being removed.
                        $duplicate->forceFill(['banned' => 1, 'AccSysID' => null])->save();
                    }
                }
            });

            $this->info(($isDryRun ? 'Dry run: ' : '').$retiredCount.' duplicate member(s) '.($isDryRun ? 'would be ' : '').'retired.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
