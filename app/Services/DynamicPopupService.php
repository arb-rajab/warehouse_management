<?php
namespace App\Services;

use App\Models\DynamicPopup;
use Illuminate\Support\Facades\Auth;

class DynamicPopupService
{
    public function getNextPopups($previous_popup)
    {
        $next_record = DynamicPopup::forAuthUser()
            ->where('id', '>', $previous_popup->id)
            ->where('status', 1)
            ->where('show_page', $previous_popup->show_page)
            ->get();

            // If next record is null, return the first record
        if (!$next_record) {
            $next_record = DynamicPopup::forAuthUser()
                ->where('status', 1)
                ->where('show_page', $previous_popup->show_page)
                ->get();
        }

        return $next_record;
    }


}
