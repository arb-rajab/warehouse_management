<?php

namespace App\Http\Controllers\Api\V2\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\UpdateWarehouseCustomerRequest;
use App\Http\Requests\Warehouse\WarehouseListRequest;
use App\Http\Resources\V2\Warehouse\CustomerCollection;
use App\Http\Resources\V2\Warehouse\CustomerDetailsResource;
use App\Http\Resources\V2\Warehouse\RepCollection;
use App\Models\StoreUser;
use App\Services\TradeLicenseStorage;

class CustomerController extends Controller
{
    /**
     * The columns CustomerCollection/RepCollection read.
     *
     * @var list<string>
     */
    private const array LIST_COLUMNS = [
        'id', 'member_serial', 'AccSysID', 'name', 'company_name', 'email', 'country', 'phone',
        'admin_verified', 'tax_number', 'bank_account_number', 'company_address', 'shipping_address',
        'is_rep', 'rep_serial', 'created_at',
    ];

    public function getCustomersList(WarehouseListRequest $request)
    {
        $customers = StoreUser::select(self::LIST_COLUMNS)
            ->where('user_type', 'customer')
            ->orderBy('id')
            ->paginate($request->perPage());

        return new CustomerCollection($customers);
    }

    public function getCustomerDetails($id)
    {
        $customer = StoreUser::find($id);
        if ($customer == null) {
            return response()->json([
                'result' => false,
                'message' => 'Customer not found',
                'status' => 404
            ]);
        }
        return new CustomerDetailsResource($customer);
    }

    public function getRepsList(WarehouseListRequest $request)
    {
        $reps = StoreUser::select(self::LIST_COLUMNS)
            ->where('user_type', 'customer')
            ->where('is_rep', 1)
            ->orderBy('id')
            ->paginate($request->perPage());

        return new RepCollection($reps);
    }

    public function update(UpdateWarehouseCustomerRequest $request, $id)
    {
        $customer = StoreUser::find($id);
        if (! $customer) {
            return response()->json([
                'result' => false,
                'message' => 'Customer not found',
                'status' => 404
            ]);
        }

        // Only what the client sent: an omitted field keeps its value.
        $customer->fill($request->safe()->except('trade_license'));

        if ($request->hasFile('trade_license')) {
            $customer->trade_license = app(TradeLicenseStorage::class)->store($request->file('trade_license'));
        }

        $customer->save();

        return response()->json([
            'result' => true,
            'message' => 'Customer updated successfully',
            'status' => 201,
            'data' => new CustomerDetailsResource($customer)
        ]);
    }
}
