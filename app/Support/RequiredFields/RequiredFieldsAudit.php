<?php

namespace App\Support\RequiredFields;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Cross-checks what each form's endpoint requires (ServerRules) against what
 * its UI marks and requires (scan-vue-forms.mjs, BladeForms), for
 * `php artisan forms:audit-required` and the ratchet test
 * (tests/Feature/RequiredFieldsRatchetTest.php). Read-only.
 *
 * Classes, per form and field (.ai/rules/form-required-fields.md):
 * - A: the server requires it, the control has no `required` (or the form has
 *   no control for it at all).
 * - B: the server requires it, its label shows no mark.
 * - C: `required` or a mark, but the server rule is optional or absent.
 * - D: a conditional server rule (`required_if`, ...) with a static
 *   `required` or mark.
 * - E: a control no server rule names (a dead field, or input the endpoint
 *   takes unvalidated).
 * - F: what could not be read: a FormRequest whose `rules()` failed, an
 *   inline rule set that is not a literal array, a form action that is not a
 *   resolvable route.
 * Hidden inputs are never A, B, C or D; `required` or a mark that depends on
 * runtime data ("dynamic") is neither a gap nor a mismatch.
 *
 * @phpstan-import-type ServerForm from ServerRules
 *
 * @phpstan-type UiControl array{name: string|null, name_resolved: bool, type: string, required: bool|string, mark: bool|string, label: string|null, disabled: bool|string, conditional: bool, file: string, line: int}
 * @phpstan-type UiForm array{file: string, line: int, module: string, tag: string, action: string, verb?: string, target: array{controller?: string, method?: string, route?: string, verb?: string}|null, controls: list<UiControl>}
 *
 * A Blade form's target is its route name and HTTP verb; a Vue form's is the
 * Wayfinder controller and method (or a route name).
 * @phpstan-type Finding array{class: string, severity: string, priority: string, module: string, kind: string, form: string, target: string, source: string, field: string, label: string|null, detail: string, ui: string|null, server: string|null, fingerprint: string}
 * @phpstan-type AuditedForm array{kind: string, module: string, priority: string, file: string, line: int, action: string, target: string, source: string, controls: int, unresolved_controls: int, findings: array<string, int>}
 * @phpstan-type AuditResult array{forms: list<AuditedForm>, findings: list<Finding>, server: array{form_requests: int, form_requests_unparsed: int, inline_validators: int, inline_unparsed: int}}
 */
final class RequiredFieldsAudit
{
    public const SEVERITY = ['A' => 'high', 'B' => 'medium', 'C' => 'high', 'D' => 'medium', 'E' => 'low', 'F' => 'info'];

    /**
     * Route names and view paths of the public, user-facing forms that are
     * fixed first: registration, addresses, checkout, contact and seller
     * registration.
     */
    private const PUBLIC_FORM_PATTERN = '/(^route:register$|registration|address|checkout|contact|^route:shops\.(store|create)$|seller.*regist)/i';

    public function __construct(private readonly ServerRules $server, private readonly string $basePath) {}

    public static function make(): self
    {
        return new self(new ServerRules(base_path()), base_path());
    }

    /**
     * The Vue forms, from the node scanner.
     *
     * @return list<UiForm>
     */
    public function vueForms(): array
    {
        $result = Process::path($this->basePath)->timeout(120)->run(['node', 'app/Support/RequiredFields/scan-vue-forms.mjs', $this->basePath]);
        if (! $result->successful()) {
            throw new RuntimeException('scan-vue-forms.mjs failed: '.$result->errorOutput());
        }
        /** @var array{files: int, forms: list<UiForm>, errors: list<array{file: string, message: string}>} $scan */
        $scan = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        if ($scan['errors'] !== []) {
            throw new RuntimeException('scan-vue-forms.mjs could not read '.json_encode($scan['errors']));
        }

        return $scan['forms'];
    }

    /**
     * @return list<UiForm>
     */
    public function bladeForms(): array
    {
        return (new BladeForms($this->basePath))->scan();
    }

    /**
     * @param  list<UiForm>  $vueForms
     * @param  list<UiForm>  $bladeForms
     * @return AuditResult
     */
    public function run(array $vueForms, array $bladeForms, bool $withInventory = true): array
    {
        $forms = [];
        $findings = [];
        foreach ([['vue', $vueForms], ['blade', $bladeForms]] as [$kind, $uiForms]) {
            foreach ($uiForms as $form) {
                [$audited, $formFindings] = $this->auditForm($kind, $form);
                $forms[] = $audited;
                array_push($findings, ...$formFindings);
            }
        }

        $server = ['form_requests' => 0, 'form_requests_unparsed' => 0, 'inline_validators' => 0, 'inline_unparsed' => 0];
        if ($withInventory) {
            $requests = $this->server->allFormRequests();
            $inline = $this->server->allInlineValidators();
            $server = [
                'form_requests' => count($requests),
                'form_requests_unparsed' => count(array_filter($requests, fn (array $form) => $form['unparsed'] !== null)),
                'inline_validators' => count($inline),
                'inline_unparsed' => count(array_filter($inline, fn (array $form) => $form['unparsed'] !== null)),
            ];
            foreach ([...$requests, ...$inline] as $serverForm) {
                if ($serverForm['unparsed'] !== null) {
                    $findings[] = $this->finding('F', 'server', $this->moduleOfSource($serverForm['source']), 'P3', $serverForm['file'], $serverForm['source'], $serverForm['source'], '-', null, 'unparsed: '.$serverForm['unparsed'], null, $serverForm['file'].':'.$serverForm['line']);
                }
            }
        }

        $unique = [];
        foreach ($findings as $finding) {
            $unique[$finding['fingerprint']] ??= $finding;
        }
        $findings = array_values($unique);
        usort($findings, fn (array $a, array $b) => [$a['class'], $a['module'], $a['form'], $a['target'], $a['field']] <=> [$b['class'], $b['module'], $b['form'], $b['target'], $b['field']]);
        usort($forms, fn (array $a, array $b) => [$a['module'], $a['file'], $a['line'], $a['target']] <=> [$b['module'], $b['file'], $b['line'], $b['target']]);

        return ['forms' => $forms, 'findings' => $findings, 'server' => $server];
    }

    /**
     * @param  UiForm  $form
     * @return array{0: AuditedForm, 1: list<Finding>}
     */
    private function auditForm(string $kind, array $form): array
    {
        $target = $this->describeTarget($form['target']);
        $action = $this->resolveAction($form['target']);
        $priority = $kind === 'vue' ? 'P3' : (preg_match(self::PUBLIC_FORM_PATTERN, $target) === 1 || preg_match(self::PUBLIC_FORM_PATTERN, basename($form['file'])) === 1 ? 'P1' : 'P2');
        $findings = [];
        $base = fn (string $class, string $field, ?string $label, string $detail, ?string $ui, ?string $server, string $source) => $this->finding($class, $kind, $form['module'], $priority, $form['file'], $target, $source, $field, $label, $detail, $ui, $server);

        $named = array_values(array_filter($form['controls'], fn (array $control) => $control['name'] !== null && $control['disabled'] !== true));
        $audited = [
            'kind' => $kind,
            'module' => $form['module'],
            'priority' => $priority,
            'file' => $form['file'],
            'line' => $form['line'],
            'action' => $form['action'],
            'target' => $target,
            'source' => '',
            'controls' => count($named),
            'unresolved_controls' => count(array_filter($form['controls'], fn (array $control) => ! $control['name_resolved'] && $control['name'] === null && $control['type'] !== 'ProductSelect')),
            'findings' => [],
        ];

        if ($action === null && $form['action'] === '(none)' && ($form['verb'] ?? 'GET') === 'GET') {
            // A search or sort form that reloads the current page: nothing to validate.
            return [$this->countFindings($audited, $findings), $findings];
        }
        if ($action === null) {
            $findings[] = $base('F', '-', null, 'form action not resolved: '.$form['action'], $form['file'].':'.$form['line'], null, '');

            return [$this->countFindings($audited, $findings), $findings];
        }

        $server = $this->server->forAction($action['controller'], $action['method']);
        $audited['source'] = $server['source'];
        if ($server['unparsed'] !== null && $server['fields'] === []) {
            $findings[] = $base('F', '-', null, 'server rules unparsed: '.$server['unparsed'], $form['file'].':'.$form['line'], $server['file'].':'.$server['line'], $server['source']);

            return [$this->countFindings($audited, $findings), $findings];
        }

        /** @var array<string, array{name: string, key: string, hidden: bool, posted: bool, required: bool|string, mark: bool|string, label: string|null, ui: string}> $fields */
        $fields = [];
        foreach ($named as $control) {
            $name = (string) $control['name'];
            $hidden = $control['type'] === 'hidden';
            $existing = $fields[$name] ?? null;
            $fields[$name] = [
                'name' => $name,
                'key' => $this->normalize($name),
                'hidden' => $existing === null ? $hidden : ($existing['hidden'] && $hidden),
                // A hidden input beside a checkbox (`is_admin` 0/1) always posts the field.
                'posted' => ($existing['posted'] ?? false) || $hidden,
                'required' => $this->combine($existing['required'] ?? false, $hidden ? false : $control['required']),
                'mark' => $this->combine($existing['mark'] ?? false, $hidden ? false : $control['mark']),
                'label' => $existing['label'] ?? $control['label'],
                'ui' => $existing['ui'] ?? $control['file'].':'.$control['line'],
            ];
        }

        $matchedServerKeys = [];
        foreach ($fields as $field) {
            if (in_array($field['name'], ['_token', '_method'], true)) {
                continue;
            }
            $rule = $this->matchRule($field['key'], $server['fields']);
            $uiRequired = $field['required'] === true || $field['mark'] === true;
            if ($rule === null) {
                if ($uiRequired && ! $field['hidden'] && $server['validated']) {
                    $findings[] = $base('C', $field['name'], $field['label'], 'required in the UI, no server rule', $field['ui'], null, $server['source']);
                } elseif ($uiRequired && ! $field['hidden']) {
                    $findings[] = $base('C', $field['name'], $field['label'], 'required in the UI, the endpoint validates nothing', $field['ui'], null, $server['source']);
                } else {
                    $findings[] = $base('E', $field['name'], $field['label'], $server['validated'] ? 'no server rule names this field' : 'the endpoint validates nothing', $field['ui'], null, $server['source']);
                }

                continue;
            }
            $matchedServerKeys[$rule['name']] = true;
            $serverAt = $rule['file'].':'.$rule['line'];
            if ($field['hidden']) {
                continue;
            }
            if ($rule['presence'] === 'required' && ! $field['posted']) {
                if ($field['required'] === false) {
                    $findings[] = $base('A', $field['name'], $field['label'], "server: {$rule['rules']}; the control has no required attribute", $field['ui'], $serverAt, $server['source']);
                }
                if ($field['mark'] === false) {
                    $findings[] = $base('B', $field['name'], $field['label'], "server: {$rule['rules']}; the label shows no required mark", $field['ui'], $serverAt, $server['source']);
                }
            } elseif ($rule['presence'] === 'conditional' && $uiRequired) {
                $findings[] = $base('D', $field['name'], $field['label'], "server: {$rule['rules']} (conditional); the UI marks it required unconditionally", $field['ui'], $serverAt, $server['source']);
            } elseif ($rule['presence'] === 'optional' && $uiRequired) {
                $findings[] = $base('C', $field['name'], $field['label'], "server: {$rule['rules']} (optional); the UI requires it", $field['ui'], $serverAt, $server['source']);
            }
        }

        foreach ($server['fields'] as $rule) {
            if ($rule['presence'] !== 'required' || isset($matchedServerKeys[$rule['name']]) || str_contains($rule['name'], '.')) {
                continue;
            }
            $findings[] = $base('A', $rule['name'], null, "server: {$rule['rules']}; the form has no control for it", $form['file'].':'.$form['line'], $rule['file'].':'.$rule['line'], $server['source']);
        }

        if ($server['unparsed'] !== null) {
            $findings[] = $base('F', '-', null, 'server rules partly unparsed: '.$server['unparsed'], $form['file'].':'.$form['line'], $server['file'].':'.$server['line'], $server['source']);
        }

        return [$this->countFindings($audited, $findings), $findings];
    }

    /**
     * @param  AuditedForm  $audited
     * @param  list<Finding>  $findings
     * @return AuditedForm
     */
    private function countFindings(array $audited, array $findings): array
    {
        $counts = [];
        foreach ($findings as $finding) {
            $counts[$finding['class']] = ($counts[$finding['class']] ?? 0) + 1;
        }
        ksort($counts);
        $audited['findings'] = $counts;

        return $audited;
    }

    private function combine(bool|string $a, bool|string $b): bool|string
    {
        if ($a === true || $b === true) {
            return true;
        }

        return $a === 'dynamic' || $b === 'dynamic' ? 'dynamic' : false;
    }

    /**
     * `items[0][qty]` and `permissions[]` as validation keys: `items.*.qty`,
     * `permissions.*`.
     */
    public function normalize(string $name): string
    {
        $key = (string) preg_replace(['/\[\]/', '/\[(\d+)\]/', '/\[([^\]]*)\]/'], ['.*', '.*', '.$1'], $name);

        return trim($key, '.');
    }

    /**
     * @param  array<string, array{name: string, presence: string, rules: string, file: string, line: int, derived: bool}>  $rules
     * @return array{name: string, presence: string, rules: string, file: string, line: int, derived: bool}|null
     */
    private function matchRule(string $key, array $rules): ?array
    {
        // A checkbox group (`levels[]`) answers its array's rule when the array is required.
        if (str_ends_with($key, '.*')) {
            $parent = $this->matchRule(substr($key, 0, -2), $rules);
            if ($parent !== null && $parent['presence'] === 'required') {
                return $parent;
            }
        }
        if (isset($rules[$key])) {
            return $rules[$key];
        }
        $segments = explode('.', $key);
        foreach ($rules as $name => $rule) {
            $ruleSegments = explode('.', (string) $name);
            if (count($ruleSegments) !== count($segments)) {
                continue;
            }
            $matches = true;
            foreach ($segments as $index => $segment) {
                if ($segment !== $ruleSegments[$index] && $segment !== '*' && $ruleSegments[$index] !== '*') {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $rule;
            }
        }
        if (str_ends_with($key, '.*')) {
            return $this->matchRule(substr($key, 0, -2), $rules);
        }

        return null;
    }

    /**
     * @param  array{controller?: string, method?: string, route?: string, verb?: string}|null  $target
     * @return array{controller: string, method: string}|null
     */
    private function resolveAction(?array $target): ?array
    {
        if ($target === null) {
            return null;
        }
        if (isset($target['controller'], $target['method'])) {
            return ['controller' => $target['controller'], 'method' => $target['method']];
        }

        return isset($target['route']) ? $this->server->actionForRoute($target['route'], $target['verb'] ?? 'POST') : null;
    }

    /**
     * @param  array{controller?: string, method?: string, route?: string, verb?: string}|null  $target
     */
    private function describeTarget(?array $target): string
    {
        if ($target === null) {
            return '(unresolved)';
        }
        if (isset($target['controller'], $target['method'])) {
            return class_basename($target['controller']).'@'.$target['method'];
        }

        return 'route:'.($target['route'] ?? '?');
    }

    private function moduleOfSource(string $source): string
    {
        if (preg_match('/^App\\\\Http\\\\(?:Requests|Controllers)\\\\(.+?)\\\\[^\\\\]+$/', $source, $match) === 1) {
            return 'server: '.str_replace('\\', '/', $match[1]);
        }

        return 'server';
    }

    /**
     * @return Finding
     */
    private function finding(string $class, string $kind, string $module, string $priority, string $form, string $target, string $source, string $field, ?string $label, string $detail, ?string $ui, ?string $server): array
    {
        return [
            'class' => $class,
            'severity' => self::SEVERITY[$class],
            'priority' => $priority,
            'module' => $module,
            'kind' => $kind,
            'form' => $form,
            'target' => $target,
            'source' => $source,
            'field' => $field,
            'label' => $label,
            'detail' => $detail,
            'ui' => $ui,
            'server' => $server,
            'fingerprint' => implode('|', [$class, $form, $target, $field]),
        ];
    }
}
