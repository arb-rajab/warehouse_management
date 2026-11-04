<?php

namespace App\Console\Commands;

use App\Support\CustomerTaxNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

#[Signature('store:tax-number-duplicates {--json : Print the report as JSON instead of tables} {--released : List the tax numbers the unique-index migration took off duplicate accounts}')]
#[Description('Report customers sharing a tax number, and blank or untrimmed values, without changing anything')]
class TaxNumberDuplicatesCommand extends Command
{
    /**
     * Read-only. Without options it previews what the
     * `add_unique_index_to_users_tax_number` migration does: how many values
     * it trims or turns into NULL, and for each shared tax number which
     * account keeps it and which have it released. It exits 1 while any
     * duplicate exists, so it can gate a deploy. `--released` lists what the
     * migration released, for staff to follow up.
     */
    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'tax_number')) {
            $this->components->error('The users table has no tax_number column.');

            return self::FAILURE;
        }

        return $this->option('released') ? $this->released() : $this->duplicates();
    }

    private function duplicates(): int
    {
        $blank = 0;
        $untrimmed = 0;

        DB::table('users')->select(['id', 'tax_number'])->whereNotNull('tax_number')->orderBy('id')->each(function (stdClass $row) use (&$blank, &$untrimmed): void {
            $normalized = CustomerTaxNumber::normalize($row->tax_number);

            if ($normalized === null) {
                $blank++;
            } elseif ($normalized !== $row->tax_number) {
                $untrimmed++;
            }
        });

        $groups = CustomerTaxNumber::duplicateGroups();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'blank' => $blank,
                'untrimmed' => $untrimmed,
                'groups' => array_map(fn (array $group): array => [
                    'tax_number' => $group['tax_number'],
                    'customers' => array_map(fn (stdClass $customer, int $rank): array => [
                        ...$this->customerRow($customer),
                        'keep' => $rank === 0,
                    ], $group['customers'], array_keys($group['customers'])),
                ], $groups),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $groups === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->line("Blank or whitespace-only tax numbers (the migration stores NULL): {$blank}");
        $this->line("Untrimmed tax numbers (the migration trims them): {$untrimmed}");
        $this->line(sprintf(
            'Duplicate tax numbers: %d group(s), %d customer(s)',
            count($groups),
            array_sum(array_map(fn (array $group): int => count($group['customers']), $groups)),
        ));

        foreach ($groups as $group) {
            $this->newLine();
            $this->line(sprintf('Tax number "%s" (%d customers)', $group['tax_number'], count($group['customers'])));
            $this->table(
                ['id', 'name', 'email', 'company', 'type', 'banned', 'archived', 'AccSysID', 'created_at', 'migration'],
                array_map(fn (stdClass $customer, int $rank): array => [
                    ...array_values(array_map(
                        fn (mixed $value): string => match (true) {
                            $value === null => '-',
                            is_bool($value) => $value ? 'yes' : 'no',
                            default => (string) $value,
                        },
                        $this->customerRow($customer),
                    )),
                    $rank === 0 ? 'keeps' : 'released (set NULL)',
                ], $group['customers'], array_keys($group['customers'])),
            );
        }

        return $groups === [] ? self::SUCCESS : self::FAILURE;
    }

    private function released(): int
    {
        $rows = Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE)
            ? DB::table(CustomerTaxNumber::RELEASES_TABLE.' as releases')
                ->leftJoin('users', 'users.id', '=', 'releases.user_id')
                ->select(['releases.user_id', 'users.name', 'users.email', 'users.tax_number as current_tax_number', 'releases.tax_number', 'releases.kept_user_id', 'releases.released_at'])
                ->orderBy('releases.id')
                ->get()
                ->map(fn (stdClass $row): array => (array) $row)
                ->all()
            : [];

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->components->info('No tax number has been released.');

            return self::SUCCESS;
        }

        $this->table(
            ['customer id', 'name', 'email', 'tax number now', 'released tax number', 'kept by customer id', 'released at'],
            array_map(fn (array $row): array => array_map(fn (mixed $value): string => $value === null ? '-' : (string) $value, array_values($row)), $rows),
        );

        return self::SUCCESS;
    }

    /**
     * @return array{id: int, name: string|null, email: string|null, company_name: string|null, user_type: string|null, banned: bool, archived: bool, AccSysID: int|null, created_at: string|null}
     */
    private function customerRow(stdClass $customer): array
    {
        return [
            'id' => (int) $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'company_name' => $customer->company_name,
            'user_type' => $customer->user_type,
            'banned' => (bool) $customer->banned,
            'archived' => (bool) $customer->is_delete,
            'AccSysID' => $customer->AccSysID === null ? null : (int) $customer->AccSysID,
            'created_at' => $customer->created_at === null ? null : (string) $customer->created_at,
        ];
    }
}
