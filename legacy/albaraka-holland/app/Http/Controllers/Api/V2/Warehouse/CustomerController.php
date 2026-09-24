<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\Warehouse\CustomerCollection;
use App\Http\Resources\V2\Warehouse\RepCollection;
use App\Http\Resources\V2\Warehouse\CustomerDetailsResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function getCustomersList()
    {

        $customers = User::where('user_type', 'customer')->get();
        return new CustomerCollection($customers);
    }


    public function getCustomerDetails($id)
    {
        $customer = User::find($id);
        if ($customer == null) {
            return response()->json([
                'result' => false,
                'message' => 'Customer not found',
                'status' => 404
            ]);
        }
        return new CustomerDetailsResource($customer);
    }

    public function getRepsList()
    {

        $reps = User::where('user_type', 'customer')->where('is_rep', 1)->get();
        return new RepCollection($reps);
    }
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes',
            'country' => 'sometimes|max:255',
            'tax_number' => 'sometimes|max:100',
            'company_address' => 'sometimes',
            'company_name' => 'sometimes',
            'shipping_address' => 'sometimes',
            'bank_account_number' => 'sometimes|max:100',
            'trade_license' => 'file|mimes:doc,docx,pdf,png,jpeg,bmb|max:20000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ]);
        }

        $customer = User::find($id);
        if($customer){
            // Update customer data
            $customer->name = $request->name;
            $customer->phone = $request->phone;
            $customer->country = $request->country;
            $customer->tax_number = $request->tax_number;
            $customer->company_address = $request->company_address;
            $customer->company_name = $request->company_name;
            $customer->shipping_address = $request->shipping_address;
            $customer->bank_account_number = $request->bank_account_number;

            // Handle trade_license file upload
            if (request()->hasFile('trade_license')) {
                $uploadedFile = request()->file('trade_license');
                $pdfFileName = $uploadedFile->getClientOriginalName(); // Get the original filename

                // Store the PDF file in the 'local' disk with the specified filename
                $pdfPath = $uploadedFile->storeAs('uploads/licenses', $pdfFileName, 'local');

                $customer->trade_license =  $pdfFileName; // Store the path in your database
            }

            $customer->save();

            return response()->json([
                'result' => true,
                'message' => 'Customer updated successfully',
                'status' => 201,
                'data' => new CustomerDetailsResource($customer)
            ]);
        }else{
            return response()->json([
                'result' => false,
                'message' => 'Customer not found',
                'status' => 404
            ]);
        }
    }
}
