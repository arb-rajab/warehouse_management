<?php

namespace App\Support\RequiredFields;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * The Blade half of `forms:audit-required`'s UI side: every `<form>` under
 * resources/views/shop, its action route (`{{ route('name', ...) }}`) and its
 * `<input|select|textarea name="...">` controls, with their `required`
 * attribute and whether their label shows a required mark
 * (<x-required-mark />, or a literal asterisk such as the legacy
 * `<span class="text-danger">*</span>`).
 *
 * Blade and HTML comments, `<script>` and `@php` blocks are blanked first
 * (line numbers are kept). A label belongs to a control by `for`/`id`, by
 * wrapping it, or by being the last label before it with no other control
 * in between; a radio or checkbox inside a `<fieldset>` whose own label
 * shows no mark takes the fieldset's `<legend>` as its label (the group's
 * name). A `required` inside `@if`, or `@required(...)`, is "dynamic";
 * a name printed with `{{ }}` is unresolved. Forms posted by JavaScript to a
 * URL that is not a `route()` call are reported with no target (class F).
 *
 * @phpstan-import-type UiForm from RequiredFieldsAudit
 * @phpstan-import-type UiControl from RequiredFieldsAudit
 */
final class BladeForms
{
    public function __construct(private readonly string $basePath) {}

    /**
     * @return list<UiForm>
     */
    public function scan(): array
    {
        $forms = [];
        $finder = Finder::create()->files()->name('*.blade.php')->in($this->basePath.'/resources/views/shop')->sortByName();
        foreach ($finder as $file) {
            $relative = 'resources/views/shop/'.str_replace('\\', '/', $file->getRelativePathname());
            array_push($forms, ...$this->scanSource((string) file_get_contents($file->getPathname()), $relative));
        }

        return $forms;
    }

    /**
     * @return list<UiForm>
     */
    public function scanSource(string $source, string $file): array
    {
        $html = $this->blankNonMarkup($source);
        $forms = [];
        $offset = 0;
        while (preg_match('/<form\b/i', $html, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $tag = $this->readTag($html, $start);
            $close = stripos($html, '</form>', $start + strlen($tag));
            $end = $close === false ? strlen($html) : $close;
            $body = substr($html, $start + strlen($tag), $end - $start - strlen($tag));
            $action = $this->attribute($tag, 'action') ?? '';
            $route = preg_match('/route\(\s*[\'"]([^\'"]+)[\'"]/', $action, $routeMatch) === 1 ? $routeMatch[1] : null;
            $verb = strtoupper($this->attribute($tag, 'method') ?? 'GET');
            if (preg_match('/@method\(\s*[\'"](\w+)[\'"]\s*\)|name=["\']_method["\'][^>]*value=["\'](\w+)["\']/i', $body, $spoof) === 1) {
                $verb = strtoupper($spoof[1] !== '' ? $spoof[1] : $spoof[2]);
            }
            $forms[] = [
                'file' => $file,
                'line' => $this->lineAt($html, $start),
                'module' => $this->module($file),
                'tag' => 'form',
                'action' => trim($action) === '' ? '(none)' : trim($action),
                'verb' => $verb,
                'target' => $route === null ? null : ['route' => $route, 'verb' => $verb],
                'controls' => $this->controls($html, $body, $start + strlen($tag), $file),
            ];
            $offset = $end + 1;
        }

        return $forms;
    }

    /**
     * Blanks Blade/HTML comments, scripts and @php blocks, keeping offsets.
     */
    private function blankNonMarkup(string $source): string
    {
        $patterns = ['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s', '/<script\b.*?<\/script>/is', '/@php\b.*?@endphp/s'];
        foreach ($patterns as $pattern) {
            $source = (string) preg_replace_callback($pattern, fn (array $m) => (string) preg_replace('/[^\n]/', ' ', $m[0]), $source);
        }

        return $source;
    }

    /**
     * The opening tag starting at $start, up to its closing `>` outside
     * quotes, `{{ }}`, `{!! !!}` and directive parentheses.
     */
    private function readTag(string $html, int $start): string
    {
        $length = strlen($html);
        $quote = null;
        $depth = 0;
        for ($i = $start + 1; $i < $length; $i++) {
            $char = $html[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if (substr($html, $i, 2) === '{{' || substr($html, $i, 3) === '{!!') {
                $close = strpos($html, $html[$i + 1] === '!' ? '!!}' : '}}', $i);
                $i = $close === false ? $length : $close + 1;

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === '>' && $depth === 0) {
                return substr($html, $start, $i - $start + 1);
            }
        }

        return substr($html, $start);
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/[\s"\']'.preg_quote($name, '/').'\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $match) === 1) {
            return ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');
        }

        return null;
    }

    /**
     * @return true|false|'dynamic'
     */
    private function booleanAttribute(string $tag, string $name): bool|string
    {
        if (preg_match('/@'.$name.'\s*\(/', $tag) === 1) {
            return 'dynamic';
        }
        $bare = (string) preg_replace(['/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/"[^"]*"/', "/'[^']*'/"], ' ', $tag);
        if (preg_match('/(?<![\w@:-])'.$name.'(?![\w-])/i', $bare) !== 1) {
            return false;
        }

        return preg_match('/@if\b.*\b'.$name.'\b.*@endif/is', $bare) === 1 ? 'dynamic' : true;
    }

    /**
     * @return list<UiControl>
     */
    private function controls(string $html, string $body, int $bodyOffset, string $file): array
    {
        $labels = [];
        $controls = [];
        preg_match_all('/<(input|select|textarea|label)\b|<\/label>/i', $body, $matches, PREG_OFFSET_CAPTURE);
        $openLabel = null;
        foreach ($matches[0] as $index => [$text, $position]) {
            $kind = strtolower($matches[1][$index][0] ?? '');
            if ($text === '</label>' || strtolower($text) === '</label>') {
                if ($openLabel !== null) {
                    $inner = substr($body, $openLabel['innerStart'], $position - $openLabel['innerStart']);
                    $labels[] = [
                        'for' => $openLabel['for'],
                        'start' => $openLabel['start'],
                        'end' => $position,
                        'text' => $this->labelText($inner),
                        'mark' => $this->hasMark($inner),
                    ];
                    $openLabel = null;
                }

                continue;
            }
            $tag = $this->readTag($body, $position);
            if ($kind === 'label') {
                $openLabel = ['for' => $this->attribute($tag, 'for'), 'start' => $position, 'innerStart' => $position + strlen($tag)];

                continue;
            }
            $name = $this->attribute($tag, 'name');
            $type = $kind === 'input' ? strtolower($this->attribute($tag, 'type') ?? 'text') : $kind;
            if (in_array($type, ['submit', 'button', 'reset', 'image'], true)) {
                continue;
            }
            $resolved = $name !== null && ! str_contains($name, '{{') && ! str_contains($name, '{!!');
            $controls[] = [
                'name' => $resolved ? $name : null,
                'value' => $type === 'hidden' ? $this->attribute($tag, 'value') : null,
                'name_resolved' => $resolved,
                'type' => $type,
                'required' => $this->booleanAttribute($tag, 'required'),
                'mark' => false,
                'label' => null,
                'disabled' => $this->booleanAttribute($tag, 'disabled'),
                'multiple' => $this->booleanAttribute($tag, 'multiple') === true,
                'conditional' => false,
                'file' => $file,
                'line' => $this->lineAt($html, $bodyOffset + $position),
                'id' => $this->attribute($tag, 'id'),
                'position' => $position,
            ];
        }

        $legends = $this->fieldsetLegends($body);
        $result = [];
        foreach ($controls as $index => $control) {
            $previousEnd = $index > 0 ? $controls[$index - 1]['position'] : -1;
            $label = null;
            foreach ($labels as $candidate) {
                if ($control['id'] !== null && $candidate['for'] === $control['id']) {
                    $label = $candidate;
                    break;
                }
                if ($candidate['start'] < $control['position'] && $candidate['end'] > $control['position']) {
                    $label = $candidate;
                    break;
                }
            }
            if ($label === null) {
                foreach (array_reverse($labels) as $candidate) {
                    if ($candidate['end'] < $control['position'] && $candidate['start'] > $previousEnd) {
                        $label = $candidate;
                        break;
                    }
                }
            }
            if (in_array($control['type'], ['radio', 'checkbox'], true) && ! ($label['mark'] ?? false)) {
                foreach ($legends as $legend) {
                    if ($legend['start'] < $control['position'] && $legend['end'] > $control['position']) {
                        $label = ['mark' => $legend['mark'], 'text' => $legend['text']];
                    }
                }
            }
            unset($control['id'], $control['position']);
            $control['mark'] = $label['mark'] ?? false;
            $control['label'] = $label['text'] ?? null;
            $result[] = $control;
        }

        return $result;
    }

    /**
     * Each `<fieldset>` with a `<legend>`: a radio group's or checkbox
     * group's label is its legend, since each option's own label names the
     * option. Inner fieldsets come after outer ones, so the innermost wins.
     *
     * @return list<array{start: int, end: int, mark: bool|string, text: string}>
     */
    private function fieldsetLegends(string $body): array
    {
        $legends = [];
        $open = [];
        preg_match_all('/<fieldset\b|<\/fieldset>/i', $body, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$text, $position]) {
            if (strtolower($text) !== '</fieldset>') {
                $open[] = $position;

                continue;
            }
            $start = array_pop($open);
            if ($start === null) {
                continue;
            }
            $fieldset = substr($body, $start, $position - $start);
            if (preg_match('/<legend\b[^>]*>(.*?)<\/legend>/is', $fieldset, $legend) === 1) {
                $legends[] = ['start' => $start, 'end' => $position, 'mark' => $this->hasMark($legend[1]), 'text' => $this->labelText($legend[1])];
            }
        }
        usort($legends, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $legends;
    }

    private function hasMark(string $inner): bool|string
    {
        if (preg_match('/@if\b.*<x-required-mark\b.*@endif/s', $inner) === 1) {
            return 'dynamic';
        }
        if (str_contains($inner, '<x-required-mark') || str_contains($inner, 'data-required-mark')) {
            return true;
        }
        $text = (string) preg_replace(['/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/<[^>]*>/'], ' ', $inner);

        return str_contains($text, '*');
    }

    private function labelText(string $inner): string
    {
        $text = (string) preg_replace_callback('/\{\{\s*translate\(\s*[\'"](.+?)[\'"]\s*\)\s*\}\}/', fn (array $m) => $m[1], $inner);
        $text = (string) preg_replace('/<x-required-mark\s*\/>/', '*', $text);
        $text = (string) preg_replace('/<x-optional-mark\s*\/>/', '(optional)', $text);
        $text = strip_tags($text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function lineAt(string $html, int $offset): int
    {
        return substr_count($html, "\n", 0, $offset) + 1;
    }

    private function module(string $file): string
    {
        $path = Str::after($file, 'resources/views/shop/');

        return match (true) {
            str_starts_with($path, 'auth/') => 'storefront/auth',
            str_starts_with($path, 'frontend/user/') => 'storefront/account',
            str_starts_with($path, 'frontend/') => 'storefront',
            str_starts_with($path, 'seller/') => 'seller panel',
            str_starts_with($path, 'backend/') => 'legacy admin (Blade)',
            default => 'storefront/'.Str::before($path, '/'),
        };
    }
}
