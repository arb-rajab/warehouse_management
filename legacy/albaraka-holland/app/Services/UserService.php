<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;

class UserService
{

    public function createCustomerOnOtajer($new_user)
    {
        return true;
        try {
            // API endpoint
            $members_endpoint = "https://stores.otajer.com/api/rest/loadmembers/" . config('services.otajer.token');
            $response = file_get_contents($members_endpoint);
            $allMembers = json_decode($response, true);

            // first let's check if the registered user already have an account in otajer! if so, don't create a new account for him in otajer, but retrieve his old data
            foreach ($allMembers['Members'] as $member) {
                // Accessing information about each user
                $otajer_user_email = $member['Email'];
                if ($otajer_user_email == $new_user->email) {
                    $new_user->AccSysID = $member['AccSystemID'];
                    $new_user->member_serial = $member['Serial'];
                    $new_user->synched_with_otajer = true;
                    $new_user->save();
                    return true;
                }
            }


            // JSON payload
            $apiEndpoint = 'https://stores.otajer.com/api/rest/addmember/' . config('services.otajer.token');
            $data = [
                "CompanyName" => $new_user->company_name,
                "Email" => $new_user->email,
                "FirstName" => $new_user->name,
                "Mobile" => $new_user->phone,
                "AccSystemID" => 161900001 + $new_user->id,
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
                $new_user->AccSysID = 161900001 + $new_user->id;
                $new_user->member_serial = $member_serial;
                $new_user->synched_with_otajer = true;
                $new_user->save();
                return true;
            } else { // If failed for any reason
                $new_user->synched_with_otajer = false;
                $new_user->save();
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Error occurred at " . Carbon::now() . ". Message: " . $e->getMessage());
            return false;
        }
    }

    public function getCoordinatesFromPostcode($postalCode, $order = null)
    {
        try {
            if (!$postalCode) {
                return $this->defaultCoordinates();
            }

            $apiKey = env('OPENCAGE_API_KEY');
            $url = "https://api.opencagedata.com/geocode/v1/json";

            $response = Http::get($url, [
                'q' => $postalCode,
                'key' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['results'])) {
                    $geometry = $data['results'][0]['geometry'];
                    $this->addLongLatToUserAddress($order?->customer, $geometry);
                    return $this->formatCoordinatesResponse($geometry, $order, $postalCode);
                }
            }

            return $this->defaultCoordinates();
        } catch (\Exception $e) {
            Log::error("Error occurred at " . Carbon::now() . ". Message: " . $e->getMessage());
            return $this->defaultCoordinates();
        }
    }

    protected function defaultCoordinates()
    {
        return [
            'latitude' => null,
            'longitude' => null,
            'label' => null,
            'user_id' => null,
            'order_id' => null,
            'order_code' => null,
            'postal_code' => null,
            'shipping_address' => null,
            'phone' => null,
            'otajer_id' => null,
        ];
    }

    protected function formatCoordinatesResponse($geometry, $order, $postalCode)
    {
        return [
            'latitude' => $geometry['lat'],
            'longitude' => $geometry['lng'],
            'label' => $order?->customer->name,
            'user_id' => $order?->customer->id,
            'order_id' => $order?->id,
            'order_code' => $order?->code,
            'postal_code' => $postalCode,
            'shipping_address' => $order?->customer->shipping_address,
            'phone' => $order?->customer->phone,
            'otajer_id' => $order?->customer->AccSysID,
        ];
    }

    public function getUserLongLat($user, $order = null)
    {
        try {
            if (!$user) {
                return $this->defaultUserCoordinates();
            }

            $user_address = $user->addresses()->whereNotNull('longitude')->first();

            if ($user_address && $user_address->latitude && $user_address->longitude) {
                return $this->formatUserCoordinates($user_address, $user);
            }

            $postalCode = $user->addresses()->whereNotNull('address')->first()?->postal_code ?? $user->shipping_address;
            return $this->getCoordinatesFromPostcode($postalCode, $order);
        } catch (\Exception $e) {
            Log::error("Error occurred at " . Carbon::now() . ". Message: " . $e->getMessage());
            return $this->defaultUserCoordinates();
        }
    }

    protected function defaultUserCoordinates()
    {
        return [
            'latitude' => null,
            'longitude' => null,
            'label' => null,
            'postal_code' => null,
        ];
    }

    protected function formatUserCoordinates($user_address, $user)
    {
        return [
            'latitude' => (string) $user_address->latitude,
            'longitude' => (string) $user_address->longitude,
            'label' => $user->name,
            'postal_code' => $user_address->address ?? $user_address->postal_code ?? $user->shipping_address,
        ];
    }

    protected function addLongLatToUserAddress($customer, $geometry)
    {
        if ($customer) {
            $customer->addresses()->each(function ($address) use ($geometry) {
                $address->latitude = $geometry['lat'];
                $address->longitude = $geometry['lng'];
                $address->save();
            });
        }
    }
}
