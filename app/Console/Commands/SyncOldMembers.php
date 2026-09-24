<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;

class SyncOldMembers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

            $members_endpoint = "https://stores.otajer.com/api/rest/loadmembers/" . config('services.otajer.token');
            $response = file_get_contents($members_endpoint);
            $allMembers = json_decode($response, true);
            $accounts_updated = 0;
            // first let's check if the registered user already have an account in otajer! if so, don't create a new account for him in otajer, but retrieve his old data
            foreach ($allMembers['Members'] as $member) {
                // Accessing information about each user in the API
                $otajer_user_email = $member['Email'];
                $user = User::where('email', $otajer_user_email)->first();
                if ($user) {
                    $user->AccSysID = $member['AccSystemID'];
                    $user->member_serial = $member['Serial'];
                    $user->synched_with_otajer = true;
                    $user->save();
                    $accounts_updated++;
                }
            }


            // create an account for every user that does not have member_serial
            // after creating an account successfully in otajer, assign the user his memeber serial (returned from the endpoint response)
            $apiEndpoint = 'https://stores.otajer.com/api/rest/addmember/' . config('services.otajer.token');

            $usersCreatedSuccessfullyCounter = 0;
            $usersFailedCounter = 0;

            $users = User::where('synched_with_otajer', false)->where('user_type', 'customer')->where('is_rep', 0)->get();
            foreach ($users as $user) {
                $data = [
                    "FirstName"    =>    $user->name,
                    "CompanyName"  =>    $user->company_name,
                    "Email"        =>    $user->email,
                    "Mobile"       =>    $user->phone,
                    "AccSystemID"  =>    161900001 + $user->id
                ];
                $client = new GuzzleClient();

                // Make POST request
                $response = $client->post($apiEndpoint, [
                    'json' => $data,
                ]);

                $responseBody = $response->getBody()->getContents();
                $responseArray = json_decode($responseBody, true);

                if (isset($responseArray['MemberID'])) {
                    $memberId = $responseArray['MemberID'];
                    $user->member_serial = $memberId;
                    $user->AccSysID =  161900001 + $user->id;
                    $user->synched_with_otajer = true;
                    $user->save();

                    $usersCreatedSuccessfullyCounter++;
                } else {

                    $usersFailedCounter++;
                }
            }

            $this->info($usersCreatedSuccessfullyCounter . " users created successfully " . "And " . $usersFailedCounter . " Failed" . " And " . $accounts_updated . " Updated");
        } catch (\Exception $e) {
            $this->info('Command failed ' . $e);
        }
    }
}
