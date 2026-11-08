<?php

namespace App\Console\Commands;

use App\Services\ProductBarcodeLabels;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * @phpstan-type Item array{barcode: string, product_id: int|null, product_name: string|null, row_id: int|null, other_product_ids: list<int>, note: string|null}
 */
#[Signature('products:barcode-conflicts
    {--json : Print the report as JSON instead of tables}
    {--csv : Print one CSV with a category column, for a file (redirect the output)}
    {--all : Also list the rows of the information categories (values that cannot be drawn or are not matched by a scan), not only their counts}
    {--fail-on-conflicts : Exit 1 while a value is on several products or a WMS barcode could not be copied}')]
#[Description('Report barcode values shared by several products, WMS barcodes the copy skipped, and values scans cannot reach, without changing anything')]
class ProductBarcodeConflictsCommand extends Command
{
    /**
     * The report's categories, in order, with their headings. `shared_value`
     * and `wms_skipped` are the conflicts --fail-on-conflicts counts; the
     * WMS categories only exist while `wms_product_barcodes` does; the last
     * two are information.
     */
    private const array CATEGORIES = [
        'shared_value' => '(a) Values on more than one product',
        'wms_skipped' => '(b) WMS barcodes not copied because another product holds the value',
        'wms_product_missing' => '(c) WMS barcodes whose product is gone',
        'other_product_id' => '(d) Values equal to another product\'s id (a scan resolves the id first)',
        'not_printable' => '(e) Values that cannot be drawn as a Code 128 label',
        'not_matched_by_scan' => '(f) Values a scan does not match as stored (Arabic-Indic digits, surrounding spaces, the legacy products.barcode column)',
    ];

    /** Categories whose rows the table output lists only with --all. */
    private const array INFORMATION = ['not_printable', 'not_matched_by_scan'];

    public function __construct(private readonly ProductBarcodeLabels $labels)
    {
        parent::__construct();
    }

    /**
     * Read-only: every query is a select. Values are compared by the
     * database (bound values against `product_barcodes.barcode`), so on MySQL
     * "equal" follows that column's collation, as the application's
     * uniqueness check and the scan lookup do. Exits 0, or with
     * --fail-on-conflicts 1 while (a) or (b) has a row: legacy duplicates may
     * never go away, so a plain run does not fail on them.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('product_barcodes')) {
            $this->components->error('The product_barcodes table does not exist.');

            return self::FAILURE;
        }

        $wms = Schema::hasTable('wms_product_barcodes');
        $report = [
            'shared_value' => $this->sharedValues(),
            'wms_skipped' => $wms ? $this->wmsSkipped() : [],
            'wms_product_missing' => $wms ? $this->wmsProductMissing() : [],
            ...$this->storedValueChecks(),
        ];
        $report['not_matched_by_scan'] = [...$report['not_matched_by_scan'], ...$this->legacyColumnValues()];

        match (true) {
            (bool) $this->option('json') => $this->printJson($report, $wms),
            (bool) $this->option('csv') => $this->printCsv($report),
            default => $this->printTables($report, $wms),
        };

        $conflicts = count($report['shared_value']) + count($report['wms_skipped']);

        return $this->option('fail-on-conflicts') && $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * (a) One item per row of each value held by two or more products.
     *
     * @return list<Item>
     */
    private function sharedValues(): array
    {
        $values = DB::table('product_barcodes')
            ->select('barcode')
            ->groupBy('barcode')
            ->havingRaw('COUNT(DISTINCT product_id) > 1')
            ->orderBy('barcode')
            ->pluck('barcode');

        $items = [];

        foreach ($values as $value) {
            $rows = DB::table('product_barcodes as pb')
                ->leftJoin('products as p', 'p.id', '=', 'pb.product_id')
                ->where('pb.barcode', $value)
                ->orderBy('pb.id')
                ->get(['pb.id', 'pb.product_id', 'pb.barcode', 'p.name']);
            $productIds = $rows->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->unique()->values();

            foreach ($rows as $row) {
                $items[] = $this->item(
                    (string) $row->barcode,
                    (int) $row->product_id,
                    $row->name,
                    (int) $row->id,
                    $productIds->reject(fn (int $id): bool => $id === (int) $row->product_id)->values()->all(),
                );
            }
        }

        return $items;
    }

    /**
     * (b) WMS rows whose product exists, whose value is not on that product
     * in `product_barcodes`, and which another product holds there.
     *
     * @return list<Item>
     */
    private function wmsSkipped(): array
    {
        $items = [];

        DB::table('wms_product_barcodes as w')
            ->join('products as p', 'p.id', '=', 'w.product_id')
            ->orderBy('w.id')
            ->select(['w.id', 'w.product_id', 'w.barcode', 'p.name'])
            ->chunk(500, function (Collection $rows) use (&$items): void {
                foreach ($rows as $row) {
                    /** @var stdClass $row */
                    $holders = DB::table('product_barcodes')->where('barcode', $row->barcode)->distinct()->orderBy('product_id')->pluck('product_id')
                        ->map(fn (mixed $id): int => (int) $id);

                    if ($holders->isNotEmpty() && ! $holders->contains((int) $row->product_id)) {
                        $items[] = $this->item((string) $row->barcode, (int) $row->product_id, $row->name, (int) $row->id, $holders->all(), 'wms_product_barcodes row');
                    }
                }
            });

        return $items;
    }

    /**
     * (c) WMS rows whose product no longer exists. The cascade should leave
     * none; they are listed should a database hold any.
     *
     * @return list<Item>
     */
    private function wmsProductMissing(): array
    {
        return DB::table('wms_product_barcodes as w')
            ->leftJoin('products as p', 'p.id', '=', 'w.product_id')
            ->whereNull('p.id')
            ->orderBy('w.id')
            ->get(['w.id', 'w.product_id', 'w.barcode'])
            ->map(fn (stdClass $row): array => $this->item((string) $row->barcode, (int) $row->product_id, null, (int) $row->id, [], 'wms_product_barcodes row'))
            ->all();
    }

    /**
     * (d), (e) and the stored half of (f), from one pass over the table.
     *
     * @return array{other_product_id: list<Item>, not_printable: list<Item>, not_matched_by_scan: list<Item>}
     */
    private function storedValueChecks(): array
    {
        $idLike = [];
        $notPrintable = [];
        $notMatched = [];

        DB::table('product_barcodes')->select(['id', 'product_id', 'barcode'])->orderBy('id')->chunk(1000, function (Collection $rows) use (&$idLike, &$notPrintable, &$notMatched): void {
            foreach ($rows as $row) {
                /** @var stdClass $row */
                $value = (string) $row->barcode;
                $normalized = $this->labels->normalize($value);

                if (preg_match('/^[1-9][0-9]{0,17}$/', $value) === 1 && (int) $value !== (int) $row->product_id) {
                    $idLike[] = $row;
                }

                if (! $this->labels->isPrintable($normalized)) {
                    $notPrintable[] = $this->item($value, (int) $row->product_id, null, (int) $row->id, [], $normalized === '' ? 'blank' : null);
                }

                if ($normalized !== '' && $normalized !== $value) {
                    $notMatched[] = $this->item($value, (int) $row->product_id, null, (int) $row->id, [], 'a scan matches "'.$value.'" only; the label shows "'.$normalized.'"');
                }
            }
        });

        $existing = DB::table('products')->whereIn('id', array_map(fn (stdClass $row): int => (int) $row->barcode, $idLike))->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)->flip();

        $otherProductId = [];

        foreach ($idLike as $row) {
            if ($existing->has((int) $row->barcode)) {
                $otherProductId[] = $this->item((string) $row->barcode, (int) $row->product_id, null, (int) $row->id, [(int) $row->barcode]);
            }
        }

        return ['other_product_id' => $otherProductId, 'not_printable' => $notPrintable, 'not_matched_by_scan' => $notMatched];
    }

    /**
     * The legacy half of (f): a `products.barcode` value the product's
     * barcodes do not hold. Scans never read that column.
     *
     * @return list<Item>
     */
    private function legacyColumnValues(): array
    {
        if (! Schema::hasColumn('products', 'barcode')) {
            return [];
        }

        $items = [];

        DB::table('products')->whereNotNull('barcode')->where('barcode', '!=', '')->orderBy('id')->select(['id', 'barcode'])
            ->chunk(500, function (Collection $rows) use (&$items): void {
                foreach ($rows as $row) {
                    /** @var stdClass $row */
                    if (! DB::table('product_barcodes')->where('product_id', $row->id)->where('barcode', $row->barcode)->exists()) {
                        $items[] = $this->item((string) $row->barcode, (int) $row->id, null, null, [], 'products.barcode column');
                    }
                }
            });

        return $items;
    }

    /**
     * @param  list<int>  $otherProductIds
     * @return Item
     */
    private function item(string $barcode, ?int $productId, ?string $productName, ?int $rowId, array $otherProductIds, ?string $note = null): array
    {
        return [
            'barcode' => $barcode,
            'product_id' => $productId,
            'product_name' => $productName,
            'row_id' => $rowId,
            'other_product_ids' => $otherProductIds,
            'note' => $note,
        ];
    }

    /**
     * @param  array<string, list<Item>>  $report
     */
    private function printJson(array $report, bool $wms): void
    {
        $this->line((string) json_encode([
            'wms_table' => $wms,
            'counts' => array_map(count(...), $report),
            'categories' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, list<Item>>  $report
     */
    private function printCsv(array $report): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return;
        }

        fputcsv($handle, ['category', 'barcode', 'product_id', 'product_name', 'row_id', 'other_product_ids', 'note'], escape: '');

        foreach ($report as $category => $items) {
            foreach ($items as $item) {
                fputcsv($handle, [
                    $category,
                    $item['barcode'],
                    $item['product_id'],
                    $item['product_name'],
                    $item['row_id'],
                    implode(' ', $item['other_product_ids']),
                    $item['note'],
                ], escape: '');
            }
        }

        rewind($handle);
        $this->output->write((string) stream_get_contents($handle));
        fclose($handle);
    }

    /**
     * @param  array<string, list<Item>>  $report
     */
    private function printTables(array $report, bool $wms): void
    {
        $this->table(['category', 'rows'], array_map(
            fn (string $category, string $heading): array => [$heading, (string) count($report[$category])],
            array_keys(self::CATEGORIES),
            self::CATEGORIES,
        ));

        if (! $wms) {
            $this->components->info('wms_product_barcodes no longer exists: categories (b) and (c) do not apply.');
        }

        foreach (self::CATEGORIES as $category => $heading) {
            $items = $report[$category];

            if ($items === [] || (in_array($category, self::INFORMATION, true) && ! $this->option('all'))) {
                continue;
            }

            $this->newLine();
            $this->line($heading);
            $this->table(
                ['barcode', 'product id', 'product name', 'row id', 'other product ids', 'note'],
                array_map(fn (array $item): array => [
                    $item['barcode'],
                    $item['product_id'] === null ? '-' : (string) $item['product_id'],
                    $item['product_name'] ?? '-',
                    $item['row_id'] === null ? '-' : (string) $item['row_id'],
                    $item['other_product_ids'] === [] ? '-' : implode(', ', $item['other_product_ids']),
                    $item['note'] ?? '-',
                ], $items),
            );
        }

        if (! $this->option('all') && count($report['not_printable']) + count($report['not_matched_by_scan']) > 0) {
            $this->newLine();
            $this->line('Run with --all to list the rows of (e) and (f).');
        }
    }
}
