<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountWebRouteVisits
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Retrieve the user's country using the `Request` facade or a third-party package
        $countryCode = $this->getCountryCodeFromRequest($request);

        // Fetch the current total visits JSON from the settings table
        $setting = get_setting('total_visits');
        $totalVisits = json_decode($setting, true);

        if ($countryCode) {
            // Increment the visit count for the user's country
            if (isset($totalVisits[$countryCode])) {
                $totalVisits[$countryCode]++;
            } else {
                $totalVisits[$countryCode] = 1;
            }
        } else {
            if (isset($totalVisits['unknown_country'])) {
                $totalVisits['unknown_country']++;
            } else {
                $totalVisits['unknown_country'] = 1;
            }
        }

        // Update the settings table with the new visit counts
        DB::table('business_settings')
            ->where('type', 'total_visits')
            ->update(['value' => json_encode($totalVisits)]);

        return $next($request);
    }

    private function getCountryCodeFromRequest($request)
    {
        try {
            $response = $this->client->get("https://api.ipgeolocation.io/ipgeo?apiKey=" . env('IP_GEO_LOCATION_KEY') . "&ip=" . $request->ip(), [
                'headers' => [
                    'Accept-Language' => 'en',
                ],
                'timeout' => env('IP_GEO_LOCATION_TIMEOUT_SECONDS'), // Timeout in seconds
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['country_name'] ?? null;
        } catch (\Exception $e) {
            // Handle geocoding errors
            return null;
        }
    }
}
