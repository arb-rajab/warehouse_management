<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Address;
use App\Models\Country;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client as GuzzleClient;


class ImportCustomersFromArray extends Command
{
    protected $signature = 'customers:import-array';
    protected $description = 'Import customers from hardcoded array';

    public function handle()
    {
        /**
         */
        $customers = User::whereIn('id', [
            2542
            ,2559
            ,4428
            ,4782
            ,4813
            ,4818
            ,4834
            ,4913
            ,4917
            ,4918
            ,4948
            ,4949
            ,4968
            ,4975
            ,4987
            ,4989
            ,5020
            ,5026
            ,5127
            ,5167
        ])->get();
        foreach ($customers as $user) {

            try {
                // استدعاء الخدمة المطلوبة

                $apiEndpoint = 'https://stores.otajer.com/api/rest/addmember/' . config('services.otajer.token');
                $data = [
                    "CompanyName" => $user->company_name,
                    "Email" => $user->email,
                    "FirstName" => $user->name,
                    "Mobile" => $user->phone,
                    "AccSystemID" => $user->AccSysID,
                ];
                $client = new GuzzleClient();

                // Make POST request
                $response = $client->post($apiEndpoint, [
                    'json' => $data,
                ]);

                $responseBody = $response->getBody()->getContents();
                $responseArray = json_decode($responseBody, true);
                // if an account created successfully in otajer, the response will return a memberID
                if (isset($responseArray['MemberID'])) {
                    $member_serial = $responseArray['MemberID'];
                    $user->member_serial = $member_serial;
                    $user->synched_with_otajer = true;
                    $user->save();
                } else { // If failed for any reason
                    $user->synched_with_otajer = false;
                    $user->save();
                }
                $this->info("Imported: {$user->email}");

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed ({$user->email}): " . $e->getMessage());
            }
        }
        $this->info('All customers processed.');
        return Command::SUCCESS;
    }
}
