<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\PolicyCollection;
use App\Models\Page;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    public function sellerPolicy()
    {
        return new PolicyCollection(Page::where('type', 'seller_policy_page')->get());
    }

    public function supportPolicy()
    {
        return new PolicyCollection(Page::where('type', 'support_policy_page')->get());
    }

    public function returnPolicy()
    {
        return new PolicyCollection(Page::where('type', 'return_policy_page')->get());
    }

    public function termsPolicy()
    {
        return new PolicyCollection(Page::where('type', 'terms_conditions_page')->get());
    }

    public function privacyPolicy()
    {
        return new PolicyCollection(Page::where('type', 'privacy_policy_page')->get());
    }
}
