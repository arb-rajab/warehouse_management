<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use stdClass;

/**
 * A customer's tax number (`users.tax_number`) is the customer's business
 * identifier: unique across the store's `users` table (a nullable unique index,
 * see the `add_unique_index_to_users_tax_number` migration), with a blank
 * value stored as NULL. The `id` stays the primary key.
 *
 * Uniqueness is always asked of the database, never compared in PHP: MySQL's
 * `utf8mb4_unicode_ci` makes the index ignore case and accents ("nl123" and
 * "NL123" collide), sqlite compares exactly, and both the validation rule and
 * the duplicate report follow whichever one is running.
 */
class CustomerTaxNumber
{
    /** The longest tax number any form accepts. */
    public const MAX_LENGTH = 100;

    /** The record of tax numbers the migration took off duplicate accounts. */
    public const RELEASES_TABLE = 'customer_tax_number_releases';

    /**
     * The value as stored: trimmed, including Unicode and non-breaking spaces,
     * and NULL when nothing is left.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;
        $trimmed = preg_replace('/^[\s\x{00A0}\x{200B}\x{FEFF}]+|[\s\x{00A0}\x{200B}\x{FEFF}]+$/u', '', $value) ?? trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * The validation rules of every form and API that writes a tax number.
     * Index 0 is the presence rule, which StoreCustomerRequest swaps for
     * `required` on the back office's customer form.
     *
     * @return list<mixed>
     */
    public static function rules(bool $required = false, int|string|null $ignoreCustomerId = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:'.self::MAX_LENGTH,
            Rule::unique('users', 'tax_number')->ignore($ignoreCustomerId),
        ];
    }

    /**
     * Every tax number more than one account holds, after trimming spaces, as
     * the database groups them. Each group lists its accounts in the order the
     * migration ranks them: the first keeps the number. An account that is
     * neither banned nor archived ranks first, then one linked to Otajer
     * (`AccSysID`), then the lowest id.
     *
     * SQL `TRIM` removes spaces only; a value padded with tabs or non-breaking
     * spaces groups here only once the migration has normalized it.
     *
     * @return list<array{tax_number: string, customers: list<stdClass>}>
     */
    public static function duplicateGroups(): array
    {
        $values = DB::table('users')
            ->selectRaw('TRIM(tax_number) as normalized')
            ->whereNotNull('tax_number')
            ->whereRaw("TRIM(tax_number) <> ''")
            ->groupByRaw('TRIM(tax_number)')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('normalized')
            ->pluck('normalized');

        return $values->map(fn (mixed $value): array => [
            'tax_number' => (string) $value,
            'customers' => self::ranked(
                DB::table('users')
                    ->select(['id', 'name', 'email', 'company_name', 'user_type', 'banned', 'is_delete', 'AccSysID', 'created_at'])
                    ->whereRaw('TRIM(tax_number) = ?', [(string) $value])
                    ->get()
            ),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, stdClass>  $customers
     * @return list<stdClass>
     */
    private static function ranked(Collection $customers): array
    {
        return $customers
            ->sortBy(fn (stdClass $customer): array => [
                ! $customer->banned && ! $customer->is_delete ? 0 : 1,
                $customer->AccSysID !== null ? 0 : 1,
                (int) $customer->id,
            ])
            ->values()
            ->all();
    }
}
