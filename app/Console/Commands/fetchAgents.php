<?php

namespace App\Console\Commands;

use App\Models\StoreUser;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class fetchAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:agents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Agents (Representatives) from Otajer to the Store';

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
    public function handle(): int
    {
        try {
            $start_time = Carbon::now();
            $members_count = 0;
            $created_accounts = 0;
            $updated_accounts = 0;


            $endpoint = config('services.otajer.url') . '/api/rest/listagents/' . config('services.otajer.token');
            $response = Http::timeout(30)->throw()->get($endpoint)->body();
            $allMembers = json_decode($response, true);
            $members_count += count($allMembers['Agents']);


            foreach ($allMembers['Agents'] as $user) {
                $email = $user['OSerial'] . env('AGENT_MAIL');
                $selectedUser = StoreUser::where('rep_id', $user['ID'])->first();
                if (! $selectedUser) {
                    $newUser = new StoreUser([
                        'name' => $user['Name'],
                        'rep_serial' => $user['OSerial'],
                        'email' => $email,
                        'country' => "Netherlands",
                        'from_api'  => true,
                        'password' => Hash::make(Str::random(40)),
                        'registration_completed' => true,
                        'synched_with_otajer' => true
                    ]);
                    $newUser->forceFill([
                        'user_type' => 'customer',
                        'rep_id' => $user['ID'],
                        'is_rep' => true,
                        'email_verified_at' => now(),
                        'admin_verified' => true,
                    ])->save();
                    if ($newUser) {
                        $created_accounts++;
                    }
                }
                // If the agent exists, update his information in case it has been changed
                else {
                    $selectedUser->forceFill([
                        // 'name' => $user['Name'],
                        'rep_serial' => $user['OSerial'],
                        'rep_id' => $user['ID'],
                        'registration_completed' => true,
                        'admin_verified' => true,
                        'synched_with_otajer' => true
                    ])->save();
                    $updated_accounts++;
                }
            }

            $end_time = Carbon::now();
            $time = (int) $end_time->diffInSeconds($start_time, true);
            $this->info($created_accounts . " agents created, " . $updated_accounts . " agents updated successfully from " . $members_count . " fetched.  Process took " . $time . ' seconds');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
