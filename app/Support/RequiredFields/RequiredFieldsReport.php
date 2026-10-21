<?php

namespace App\Support\RequiredFields;

/**
 * Renders a RequiredFieldsAudit result as the committed
 * `.ai/required-fields-audit-<date>.md` and `.json`.
 *
 * @phpstan-import-type AuditResult from RequiredFieldsAudit
 * @phpstan-import-type AuditedForm from RequiredFieldsAudit
 * @phpstan-import-type Finding from RequiredFieldsAudit
 */
final class RequiredFieldsReport
{
    public const CLASSES = ['A', 'B', 'C', 'D', 'E', 'F'];

    private const CLASS_TITLES = [
        'A' => 'server-required, no `required` attribute (or no control at all)',
        'B' => 'server-required, no visible mark',
        'C' => '`required` or a mark, but the server rule is optional or absent',
        'D' => 'conditional server rule, static `required` or mark',
        'E' => 'control with no matching server rule',
        'F' => 'unparsed (server rules or form action)',
    ];

    /**
     * @param  AuditResult  $result
     */
    public function __construct(private readonly array $result, private readonly string $date) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'generator' => 'php artisan forms:audit-required',
            'totals' => $this->totals(),
            'modules' => $this->byModule(),
            'server' => $this->result['server'],
            'top' => $this->top(20),
            'forms' => $this->result['forms'],
            'findings' => array_map(fn (array $finding) => array_diff_key($finding, ['fingerprint' => true]), $this->result['findings']),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array{forms: array{vue: int, blade: int}, by_class: array<string, int>, by_severity: array<string, int>, by_priority: array<string, int>}
     */
    public function totals(): array
    {
        $byClass = array_fill_keys(self::CLASSES, 0);
        $bySeverity = ['high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        $byPriority = ['P1' => 0, 'P2' => 0, 'P3' => 0];
        foreach ($this->result['findings'] as $finding) {
            $byClass[$finding['class']]++;
            $bySeverity[$finding['severity']]++;
            $byPriority[$finding['priority']]++;
        }

        return [
            'forms' => [
                'vue' => count(array_filter($this->result['forms'], fn (array $form) => $form['kind'] === 'vue')),
                'blade' => count(array_filter($this->result['forms'], fn (array $form) => $form['kind'] === 'blade')),
            ],
            'by_class' => $byClass,
            'by_severity' => $bySeverity,
            'by_priority' => $byPriority,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function byModule(): array
    {
        $modules = [];
        foreach ($this->result['forms'] as $form) {
            $modules[$form['module']] ??= ['forms' => 0, ...array_fill_keys(self::CLASSES, 0)];
            $modules[$form['module']]['forms']++;
        }
        foreach ($this->result['findings'] as $finding) {
            $modules[$finding['module']] ??= ['forms' => 0, ...array_fill_keys(self::CLASSES, 0)];
            $modules[$finding['module']][$finding['class']]++;
        }
        ksort($modules);

        return $modules;
    }

    /**
     * The forms to fix first: public forms before the rest, then by the
     * number of high (A, C) and medium (B, D) findings.
     *
     * @return list<array{rank: int, priority: string, module: string, form: string, target: string, high: int, medium: int, low: int, findings: array<string, int>}>
     */
    public function top(int $limit): array
    {
        $ranked = [];
        foreach ($this->result['forms'] as $form) {
            $counts = $form['findings'];
            $high = ($counts['A'] ?? 0) + ($counts['C'] ?? 0);
            $medium = ($counts['B'] ?? 0) + ($counts['D'] ?? 0);
            if ($high + $medium === 0) {
                continue;
            }
            $ranked[] = [
                'priority' => $form['priority'],
                'module' => $form['module'],
                'form' => $form['file'].':'.$form['line'],
                'target' => $form['target'],
                'high' => $high,
                'medium' => $medium,
                'low' => $counts['E'] ?? 0,
                'findings' => $counts,
            ];
        }
        usort($ranked, fn (array $a, array $b) => [$a['priority'], -$a['high'], -$a['medium'], -$a['low'], $a['form']] <=> [$b['priority'], -$b['high'], -$b['medium'], -$b['low'], $b['form']]);

        return array_map(fn (array $row, int $index) => ['rank' => $index + 1, ...$row], array_slice($ranked, 0, $limit), array_keys(array_slice($ranked, 0, $limit)));
    }

    public function toMarkdown(): string
    {
        $totals = $this->totals();
        $server = $this->result['server'];
        $lines = [
            "# Required-fields audit ({$this->date})",
            '',
            'Generated by `php artisan forms:audit-required` (read-only). It compares what each form endpoint',
            "requires (FormRequest `rules()` and inline validators) with what the form's controls require and mark.",
            'The convention, the classes and how to read this file: [.ai/rules/form-required-fields.md](rules/form-required-fields.md).',
            '',
            '## Summary',
            '',
            "Forms scanned: **{$totals['forms']['vue']}** Vue (resources/js/pages, resources/js/components) and **{$totals['forms']['blade']}** Blade (resources/views/shop).",
            "Server rules: **{$server['form_requests']}** FormRequests ({$server['form_requests_unparsed']} unparsed) and **{$server['inline_validators']}** controller actions with inline validation ({$server['inline_unparsed']} unparsed).",
            '',
            '| Class | Meaning | Severity | Findings |',
            '| --- | --- | --- | ---: |',
        ];
        foreach (self::CLASSES as $class) {
            $lines[] = "| {$class} | ".self::CLASS_TITLES[$class].' | '.RequiredFieldsAudit::SEVERITY[$class]." | {$totals['by_class'][$class]} |";
        }
        $lines[] = '| | **Total** | | **'.array_sum($totals['by_class']).'** |';
        $lines[] = '';
        $lines[] = 'By severity: '.implode(', ', array_map(fn (string $key, int $count) => "{$key} {$count}", array_keys($totals['by_severity']), $totals['by_severity'])).'.';
        $lines[] = 'By priority: '.implode(', ', array_map(fn (string $key, int $count) => "{$key} {$count}", array_keys($totals['by_priority']), $totals['by_priority'])).' (P1 public storefront forms: registration, address, checkout, contact, seller registration; P2 other storefront, account and seller-panel forms; P3 back office).';
        $lines[] = '';
        $lines[] = '## Per module';
        $lines[] = '';
        $lines[] = '| Module | Forms | A | B | C | D | E | F |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';
        foreach ($this->byModule() as $module => $counts) {
            $lines[] = "| {$module} | {$counts['forms']} | {$counts['A']} | {$counts['B']} | {$counts['C']} | {$counts['D']} | {$counts['E']} | {$counts['F']} |";
        }
        $lines[] = '';
        $lines[] = '## Top 20 forms to fix first';
        $lines[] = '';
        $lines[] = 'Public forms first, then by high (A + C) and medium (B + D) findings.';
        $lines[] = '';
        $lines[] = '| # | Priority | Form | Posts to | High | Medium | Low (E) |';
        $lines[] = '| ---: | --- | --- | --- | ---: | ---: | ---: |';
        foreach ($this->top(20) as $row) {
            $lines[] = "| {$row['rank']} | {$row['priority']} | `{$row['form']}` | `{$row['target']}` | {$row['high']} | {$row['medium']} | {$row['low']} |";
        }
        $lines[] = '';
        $lines[] = '## Findings by module and form';
        $lines[] = '';
        $lines[] = 'Each line: class, field (label), what differs, then the UI and server locations.';

        $grouped = [];
        foreach ($this->result['findings'] as $finding) {
            $grouped[$finding['module']][$finding['form'].' → '.$finding['target']][] = $finding;
        }
        ksort($grouped);
        foreach ($grouped as $module => $forms) {
            $lines[] = '';
            $lines[] = "### {$module}";
            ksort($forms);
            foreach ($forms as $form => $findings) {
                $lines[] = '';
                $lines[] = "- `{$form}`".($findings[0]['source'] !== '' ? " ({$findings[0]['source']})" : '');
                foreach ($findings as $finding) {
                    $label = $finding['label'] !== null && $finding['label'] !== '' ? ' ('.str_replace('|', '/', $finding['label']).')' : '';
                    $where = array_filter(['UI '.($finding['ui'] ?? '-'), $finding['server'] !== null ? 'server '.$finding['server'] : null]);
                    $lines[] = "  - **{$finding['class']}** `{$finding['field']}`{$label}: {$finding['detail']}. ".implode(', ', $where);
                }
            }
        }
        $lines[] = '';

        return implode("\n", $lines);
    }
}
