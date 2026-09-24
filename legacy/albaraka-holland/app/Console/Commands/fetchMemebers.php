<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;

class fetchMemebers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:members';

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
            $start_time = Carbon::now();
            $members_count = 0;
            $created_accounts = 0;
            $updated_accounts = 0;


            $endpoint = 'https://stores.otajer.com/api/rest/loadmembers/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);
            $allMembers = json_decode($response, true);
            $members_count += count($allMembers['Members']);


            foreach ($allMembers['Members'] as $user) {
                if ($user['Email'] != null) {
                    $selectedUser = User::where('email', $user['Email'])->first();
                    $country_name = $user['CountryenName'] == 'Holland' ? 'Netherlands' : $user['CountryenName'];
                    if (! $selectedUser) {
                        if(! User::where('AccSysID', $user['AccSystemID'])->exists())
                        {
                            $newUser = User::create([
                                'name' => $user['FirstName'] . " " . $user['LastName'],
                                'user_type' => 'customer',
                                'company_name' => $user['CompanyName'] ? $user['CompanyName'] : 'none',
                                'email' => $user['Email'],
                                'tax_number' => $user['TaxNumber'],
                                'country' => $country_name,
                                'from_api'  => true,
                                'member_serial' => $user['Serial'],
                                'email_verified_at' => date('Y-m-d H:m:s'),
                                'AccSysID' => intval($user['AccSystemID']),
                                'password' => Hash::make("123456"),
                                'registration_completed' => false,
                                'synched_with_otajer' => true
                            ]);
                            if ($newUser) {
                                $created_accounts++;
                            }
                        }
                    }
                    // If the customer exists, update his information in case it has been changed
                    else {
                        $selectedUser->update([
                            'member_serial' => $user['Serial'],
                            'AccSysID' => intval($user['AccSystemID']),
                            'synched_with_otajer' => true
                        ]);
                        $updated_accounts++;
                    }
                }
            }

            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($created_accounts . " members created " . $updated_accounts . " members updated successfully from " . $members_count . " fetched.  Process took " . $time . ' seconds');
        } catch (Exception $e) {
            $this->info('Command failed ' . $e);
        }
    }
}
