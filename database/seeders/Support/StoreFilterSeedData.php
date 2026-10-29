<?php

namespace Database\Seeders\Support;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Only registry-owned records may be updated by filter fixtures. */
class StoreFilterSeedData
{
    public const string TRACKING_KEY = 'store_filter_seed_v1';

    public static function guard(): void
    {
        if (! app()->environment('local', 'development', 'dev', 'staging', 'testing')) {
            throw new RuntimeException('Store filter fixtures refuse execution in '.app()->environment().'.');
        }
    }

    /** @return list<array{table_name: string, seed_key: string, record_id: string}> */
    public function records(): array
    {
        return $this->decodeTracking($this->trackingRow()?->value);
    }

    public function find(string $table, string $key): int|string|null
    {
        foreach ($this->records() as $record) {
            if ($record['table_name'] === $table && $record['seed_key'] === $key
                && DB::table($table)->where('id', $record['record_id'])->exists()) {
                return ctype_digit($record['record_id']) ? (int) $record['record_id'] : $record['record_id'];
            }
        }

        return null;
    }

    private function trackingRow(bool $lock = false): ?\stdClass
    {
        $query = DB::table('business_settings')->where('type', self::TRACKING_KEY);
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($rows->count() > 1) {
            throw new RuntimeException('Duplicate store filter tracking settings; refusing to claim existing records.');
        }

        return $rows->first();
    }

    /** @return list<array{table_name: string, seed_key: string, record_id: string}> */
    private function decodeTracking(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $payload = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ($payload['version'] ?? null) !== 1
            || ! isset($payload['records']) || ! is_array($payload['records']) || ! array_is_list($payload['records'])) {
            throw new RuntimeException('Reserved store filter setting is not a valid fixture index; refusing to overwrite it.');
        }
        $records = [];
        $keys = [];
        $ids = [];
        foreach ($payload['records'] as $record) {
            if (! is_array($record) || ! isset($record['table_name'], $record['seed_key'], $record['record_id'])
                || ! is_string($record['table_name']) || ! is_string($record['seed_key']) || ! is_string($record['record_id'])) {
                throw new RuntimeException('Invalid store filter tracking record.');
            }
            $key = $record['table_name']."\0".$record['seed_key'];
            $id = $record['table_name']."\0".$record['record_id'];
            if (isset($keys[$key]) || isset($ids[$id])) {
                throw new RuntimeException('Duplicate store filter ownership key or record.');
            }
            $keys[$key] = true;
            $ids[$id] = true;
            $records[] = ['table_name' => $record['table_name'], 'seed_key' => $record['seed_key'], 'record_id' => $record['record_id']];
        }

        return $records;
    }

    /** @param array<string, mixed> $data */
    public function row(string $table, string $key, array $data): int|string
    {
        self::guard();

        return DB::transaction(function () use ($table, $key, $data): int|string {
            // Serialize first-time index creation using an existing schema-history row.
            DB::table(config('database.migrations.table'))->orderBy('id')->lockForUpdate()->first();
            $tracking = $this->trackingRow(lock: true);
            if ($tracking !== null && $tracking->value === null) {
                throw new RuntimeException('Reserved store filter setting already exists without a fixture index.');
            }
            $records = $this->decodeTracking($tracking?->value);
            $position = null;
            $id = null;
            foreach ($records as $index => $record) {
                if ($record['table_name'] === $table && $record['seed_key'] === $key) {
                    $position = $index;
                    if (DB::table($table)->where('id', $record['record_id'])->exists()) {
                        $id = ctype_digit($record['record_id']) ? (int) $record['record_id'] : $record['record_id'];
                    }
                    break;
                }
            }
            if ($id !== null) {
                unset($data['id']);
                if ($table === 'users') {
                    unset($data['password']);
                }
                if ($table === 'products') {
                    Product::query()->findOrFail($id)->forceFill($data)->saveQuietly();
                } elseif ($data !== []) {
                    DB::table($table)->where('id', $id)->update($data);
                }

                return $id;
            }

            if (Schema::hasColumn($table, 'created_at')) {
                $data += ['created_at' => now()];
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $data += ['updated_at' => now()];
            }
            if ($table === 'products') {
                $product = new Product;
                $product->forceFill($data)->saveQuietly();
                $id = $product->getKey();
            } elseif (isset($data['id'])) {
                DB::table($table)->insert($data);
                $id = $data['id'];
            } else {
                $id = DB::table($table)->insertGetId($data);
            }
            $record = ['table_name' => $table, 'seed_key' => $key, 'record_id' => (string) $id];
            if ($position === null) {
                $records[] = $record;
            } else {
                $records[$position] = $record;
            }
            $value = json_encode(['version' => 1, 'records' => $records], JSON_THROW_ON_ERROR);
            if ($tracking === null) {
                DB::table('business_settings')->insert(['type' => self::TRACKING_KEY, 'value' => $value]);
            } else {
                DB::table('business_settings')->where('id', $tracking->id)->update(['value' => $value]);
            }

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function user(string $key, array $data = []): int
    {
        self::guard();
        if ($this->find('users', $key) === null) {
            $password = (string) (getenv('STORE_FILTER_SEED_PASSWORD') ?: config('store_filter_seed.password', ''));
            if (strlen($password) < 16) {
                throw new RuntimeException('Set STORE_FILTER_SEED_PASSWORD to at least 16 characters before creating filter fixture accounts.');
            }
            $type = in_array($key, ['admin', 'seller', 'driver', 'staff'], true) ? $key : 'customer';
            $data += [
                'name' => 'Filter fixture '.$key,
                'email' => 'store-filter-'.$key.'@example.invalid',
                'password' => Hash::make($password),
                'user_type' => $type,
                'is_rep' => $key === 'representative' ? 1 : 0,
                'admin_verified' => 1,
                'email_verified_at' => now(),
                'registration_completed' => 1,
                'is_important' => 1,
                'activity_status' => 'new_user',
                'is_delete' => null,
            ];
        }

        return (int) $this->row('users', $key, $data);
    }
}
