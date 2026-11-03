<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;

/**
 * A customers/representatives list row: the narrow {@see CustomerResource} record plus every
 * property the list can show as a column. The controller selects only the columns the viewer
 * may see (tax number, bank account, trade license and balance need `edit_customer`), and a
 * key whose attribute was not selected is left out. Secrets (password hash, remember token,
 * verification codes, device and API tokens) are never listed here.
 *
 * @property-read StoreUser $resource
 */
class CustomerListResource extends CustomerResource
{
    /** Plain text and number columns, passed through as stored. */
    public const array PLAIN_ATTRIBUTES = ['user_type', 'country', 'address', 'state', 'city', 'postal_code', 'company_address', 'shipping_address', 'member_serial', 'AccSysID', 'admin_notes', 'admin_verified_at', 'last_order_at', 'rep_discount_percentage'];

    /** Columns only an admin who may edit customers receives. */
    public const array RESTRICTED_ATTRIBUTES = ['tax_number', 'trade_license', 'bank_account_number', 'balance'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $selected = $user->getAttributes();
        $record = parent::toArray($request);

        foreach ([...self::PLAIN_ATTRIBUTES, ...self::RESTRICTED_ATTRIBUTES] as $attribute) {
            if (array_key_exists($attribute, $selected)) {
                $record[$attribute] = $user->getAttribute($attribute);
            }
        }
        foreach (['registration_completed', 'from_api'] as $flag) {
            if (array_key_exists($flag, $selected)) {
                $record[$flag] = (bool) $user->getAttribute($flag);
            }
        }
        if (array_key_exists('profits', $selected)) {
            $record['profits'] = (float) $user->getAttribute('profits');
        }
        foreach (['email_verified_at', 'created_at', 'updated_at'] as $timestamp) {
            if (array_key_exists($timestamp, $selected)) {
                $record[$timestamp] = $user->getAttribute($timestamp)?->toIso8601String();
            }
        }

        return $record;
    }
}
