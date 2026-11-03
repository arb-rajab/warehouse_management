<?php

namespace App\Console\Commands;

use App\Models\StoreUser;
use App\Support\CustomerTaxNumber;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
    public function handle(): int
    {
        try {
            $start_time = Carbon::now();
            $members_count = 0;
            $created_accounts = 0;
            $updated_accounts = 0;
            $tax_number_conflicts = 0;


            $endpoint = config('services.otajer.url') . '/api/rest/loadmembers/' . config('services.otajer.token');
            $response = Http::timeout(30)->throw()->get($endpoint)->body();
            $allMembers = json_decode($response, true);
            $members_count += count($allMembers['Members']);


            foreach ($allMembers['Members'] as $user) {
                if ($user['Email'] != null) {
                    $selectedUser = StoreUser::where('email', $user['Email'])->first();
                    $country_name = $user['CountryenName'] == 'Holland' ? 'Netherlands' : $user['CountryenName'];
                    if (! $selectedUser) {
                        if(! StoreUser::where('AccSysID', $user['AccSystemID'])->exists())
                        {
                            // The tax number is unique: a member whose number another
                            // customer already holds is still imported, without it.
                            $tax_number = CustomerTaxNumber::normalize($user['TaxNumber'] ?? null);
                            $holder_id = $tax_number === null ? null : StoreUser::where('tax_number', $tax_number)->value('id');
                            if ($holder_id !== null) {
                                $this->warn('Member '.$user['Email'].' (AccSysID '.$user['AccSystemID'].') imported without tax number '.$tax_number.': customer #'.$holder_id.' already has it.');
                                $tax_number = null;
                                $tax_number_conflicts++;
                            }
                            $newUser = new StoreUser([
                                'name' => $user['FirstName'] . " " . $user['LastName'],
                                'company_name' => $user['CompanyName'] ? $user['CompanyName'] : 'none',
                                'email' => $user['Email'],
                                'tax_number' => $tax_number,
                                'country' => $country_name,
                                'from_api'  => true,
                                'member_serial' => $user['Serial'],
                                'AccSysID' => intval($user['AccSystemID']),
                                'password' => Hash::make(Str::random(40)),
                                'registration_completed' => false,
                                'synched_with_otajer' => true
                            ]);
                            $newUser->forceFill([
                                'user_type' => 'customer',
                                'email_verified_at' => now(),
                            ])->save();
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
            $time = (int) $end_time->diffInSeconds($start_time, true);
            $this->info($created_accounts . " members created " . $updated_accounts . " members updated successfully from " . $members_count . " fetched.  Process took " . $time . ' seconds');
            if ($tax_number_conflicts > 0) {
                $this->warn($tax_number_conflicts . ' member(s) imported without their tax number, which another customer already has.');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
