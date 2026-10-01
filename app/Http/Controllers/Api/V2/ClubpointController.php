<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\ClubpointCollection;
use App\Models\ClubPoint;
use App\Models\StoreUser;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubpointController extends Controller
{

    public function get_list()
    {
        $club_points = ClubPoint::where('user_id', auth()->user()->id)->latest()->paginate(10);

        return new ClubpointCollection($club_points);
    }

    public function convert_into_wallet(Request $request)
    {
        $converted = DB::transaction(function () use ($request) {
            // Only the signed-in account's own points, and each set once: the row
            // is locked so a second request waits and finds it converted.
            $club_point = ClubPoint::where('id', $request->id)
                ->where('user_id', auth()->user()->id)
                ->lockForUpdate()
                ->first();

            if ($club_point === null || $club_point->convert_status != 0) {
                return false;
            }

            $amount = 0;

            foreach ($club_point->club_point_details as $club_point_detail) {
                if ($club_point_detail->refunded == 0) {
                    $club_point_detail->converted_amount = floatval($club_point_detail->point / get_setting('club_point_convert_rate'));
                    $club_point_detail->save();
                    $amount += $club_point_detail->converted_amount;
                }
            }

            $wallet = new Wallet;
            $wallet->user_id = auth()->user()->id;
            $wallet->amount = $amount;
            $wallet->payment_method = 'Club Point Convert';
            $wallet->payment_details = 'Club Point Convert';
            $wallet->save();

            $user = StoreUser::whereKey(auth()->user()->id)->lockForUpdate()->first();
            $user->balance = $user->balance + $amount;
            $user->save();

            $club_point->convert_status = 1;
            $club_point->save();

            return true;
        });

        if (! $converted) {
            return response()->json([
                'success' => false,
                'message' => translate('Something went wrong')
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Successfully converted')
        ]);
    }
}
