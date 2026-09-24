<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;

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
    public function handle()
    {
        try {
            $start_time = Carbon::now();
            $members_count = 0;
            $created_accounts = 0;
            $updated_accounts = 0;


            $endpoint = 'https://stores.otajer.com/api/rest/listagents/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);
            $allMembers = json_decode($response, true);
            $members_count += count($allMembers['Agents']);


            foreach ($allMembers['Agents'] as $user) {
                $email = $user['OSerial'] . env('AGENT_MAIL');
                $selectedUser = User::where('rep_id', $user['ID'])->first();
                if (! $selectedUser) {
                    $newUser = User::create([
                        'name' => $user['Name'],
                        'user_type' => 'customer',
                        'rep_serial' => $user['OSerial'],
                        'rep_id' => $user['ID'],
                        'is_rep' => true,
                        'email' => $email,
                        'country' => "Netherlands",
                        'from_api'  => true,
                        'email_verified_at' => date('Y-m-d H:m:s'),
                        'password' => Hash::make("123456"),
                        'registration_completed' => true,
                        'admin_verified' => true,
                        'synched_with_otajer' => true
                    ]);
                    if ($newUser) {
                        $created_accounts++;
                    }
                }
                // If the agent exists, update his information in case it has been changed
                else {
                    $selectedUser->update([
                        // 'name' => $user['Name'],
                        'rep_serial' => $user['OSerial'],
                        'rep_id' => $user['ID'],
                        'registration_completed' => true,
                        'admin_verified' => true,
                        'synched_with_otajer' => true
                    ]);
                    $updated_accounts++;
                }
            }

            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($created_accounts . " agents created, " . $updated_accounts . " agents updated successfully from " . $members_count . " fetched.  Process took " . $time . ' seconds');
        } catch (Exception $e) {
            $this->info('Command failed ' . $e);
        }
    }
}
