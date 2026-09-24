<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;

class fixMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:members';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import members from otajer API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $endpoint = 'https://stores.otajer.com/api/rest/loadmembers/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);
            $allMembers = json_decode($response, true);


            foreach ($allMembers['Members'] as $user) {
                if ($user['AccSystemID'] != null) {
                    User::where('AccSysID', $user['AccSystemID'])->update([
                        'member_serial' => $user['Serial'],
                    ]);
                    $users = User::where('AccSysID', $user['AccSystemID'])->get();
                    if($users->count() > 1)
                    {
                        $requiredUser = $users->where('profits', '=', 0)->first();
                        if($requiredUser)
                            $requiredUser->delete();
                    }
                }
            }
            $this->info('done');
        } catch (Exception $e) {
            $this->info('Command failed ' . $e);
        }
    }
}
