<?php

use Illuminate\Support\Facades\Blade;

/**
 * Inspect executable PHP, including compiled Blade expressions, without rendering.
 * Tokenization excludes PHP comments/strings containing example calls and inline
 * HTML (including SVG transforms and ordinary JavaScript method calls).
 *
 * @return list<string>
 */
function localizationBoundaryTokens(string $source, bool $blade = false): array
{
    $tokens = [];
    foreach (token_get_all($blade ? Blade::compileString($source) : $source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
                continue;
            }
            $tokens[] = $token[1];
        } else {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Retain arguments and fluent calls so an allowed file cannot silently add a
 * different database query. Whitespace/comments do not affect the signature.
 *
 * @param  list<string>  $tokens
 */
function localizationBoundaryExpression(array $tokens, int $start, bool $firstCallOnly = false): string
{
    $expression = '';
    $depth = 0;
    $opened = false;
    for ($index = $start; $index < count($tokens); $index++) {
        $token = $tokens[$index];
        if ($depth === 0 && in_array($token, [';', ',', ')', ']', '}', '{', '=>', '=', '?', ':'], true)) {
            break;
        }
        $expression .= $token;
        if (in_array($token, ['(', '[', '{'], true)) {
            $depth++;
            $opened = true;
        } elseif (in_array($token, [')', ']', '}'], true)) {
            $depth--;
            if ($firstCallOnly && $opened && $depth === 0) {
                break;
            }
        }
    }

    return $expression;
}

/**
 * Literal table names also detect aliases, alternate query builders and raw SQL.
 * Payload/validation array keys and validated('translations') are not table reads.
 * Files selecting a catalog table must additionally pin every table query in that
 * file, including queries through its $table variable. This is a source boundary,
 * not data-flow analysis of arbitrary constructed names or dynamic PHP callables.
 *
 * @return array<string, int> expression => occurrence count
 */
function localizationBoundaryEvents(string $source, bool $blade = false): array
{
    $tokens = localizationBoundaryTokens($source, $blade);
    $events = [];
    $catalogOwner = false;
    foreach ($tokens as $index => $token) {
        $previous = $tokens[$index - 1] ?? '';
        $next = $tokens[$index + 1] ?? '';
        $event = null;
        if (strtolower(ltrim($token, '\\')) === 'translate' && $next === '(' && ! in_array($previous, ['->', '?->', '::'], true)) {
            $event = ($previous === 'function' ? 'definition:' : 'helper:').localizationBoundaryExpression($tokens, $index, true);
        }
        if (strtolower(ltrim($token, '\\')) === 'translate' && $previous === 'function' && $next !== '(') {
            $event = 'helper-alias:'.localizationBoundaryExpression($tokens, $index - 1);
        }
        $class = substr(strrchr('\\'.$token, '\\'), 1);
        if (in_array($class, ['Translation', 'AppTranslation', 'TranslationUtility'], true)) {
            $start = in_array($previous, ['use', 'new', 'class'], true) ? $index - 1 : $index;
            $event = 'model:'.localizationBoundaryExpression($tokens, $start);
        }
        if (in_array($token, ["'translations'", '"translations"', "'app_translations'", '"app_translations"'], true)
            && $next !== '=>'
            && ! ($previous === '(' && ($tokens[$index - 2] ?? '') === 'validated')) {
            $catalogOwner = true;
            $event = 'table-name:'.$token;
        }
        if (preg_match('/^[\'"](?:[^\'"]*\b)(?:from|join|into|update)\s+[`"]?(?:app_translations|translations)\b/i', $token) === 1) {
            $event = 'sql:'.$token;
        }
        if ($event !== null) {
            $events[$event] = ($events[$event] ?? 0) + 1;
        }
    }
    if ($catalogOwner) {
        foreach ($tokens as $index => $token) {
            if (in_array(strtolower($token), ['table', 'from', 'join', 'leftjoin', 'rightjoin', 'exists'], true)
                && ($tokens[$index + 1] ?? '') === '('
                && in_array($tokens[$index - 1] ?? '', ['::', '->', '?->'], true)) {
                $event = 'query:'.localizationBoundaryExpression($tokens, max(0, $index - 2));
                $events[$event] = ($events[$event] ?? 0) + 1;
            }
        }
    }
    ksort($events);

    return $events;
}

test('repository static PHP and Blade text cannot acquire database translation dependencies', function () {
    // Exact expressions/counts, never file or directory exemptions. The 14
    // remaining helper calls translate business-owned content, not static UI.
    $allowed = [
        'app/Support/StorePermissions.php' => [
            'helper:translate(Str::headline($name))' => 1,
            'helper:translate(Str::headline($section))' => 1,
        ],
        'app/Support/StoreLocale.php' => [
            "helper:translate(ucfirst(str_replace('_',' ',(string)\$value)))" => 1, // Custom payment names only.
        ],
        'app/Http/Resources/V2/LanguageCollection.php' => [
            'helper:translate($data->name)' => 1, // Configured language names, not mobile catalog keys.
        ],
        'resources/views/shop/seller/product_query/index.blade.php' => [
            'helper:translate($query->user->name)' => 1,
            'helper:translate($query->product->name)' => 1,
            'helper:translate(Str::limit($query->question,100))' => 1,
            'helper:translate(Str::limit($query->reply,100))' => 1,
        ],
        'resources/views/shop/frontend/index.blade.php' => [
            "helper:translate(get_setting('cupon_title'))" => 1,
            "helper:translate(get_setting('cupon_subtitle'))" => 1,
        ],
        'resources/views/shop/frontend/inc/nav.blade.php' => [
            'helper:translate($value)' => 2, // Configured desktop/mobile header_menu_labels.
        ],
        'resources/views/shop/frontend/product_details/details.blade.php' => [
            'helper:translate($detailedProduct->external_link_btn)' => 1,
        ],
        'resources/views/shop/frontend/partials/addToCart.blade.php' => [
            'helper:translate($product->external_link_btn)' => 1,
        ],
        // The content helper retains English insertion, locale/default fallback
        // and translations-<content locale> caches. Static UI must not call it.
        'app/Http/Helpers.php' => [
            'definition:translate($key,$lang=null,$addslashes=false)' => 1,
            'model:useApp\\Models\\Translation' => 1,
            'model:newTranslation' => 1,
            "model:Translation::where('lang','en')->pluck('lang_value','lang_key')->toArray()" => 1,
            "model:Translation::where('lang',\$lang)->pluck('lang_value','lang_key')->toArray()" => 1,
            "model:Translation::where('lang',config('app.default_language'))->pluck('lang_value','lang_key')->toArray()" => 1,
        ],
        'app/Models/Translation.php' => [
            'model:classTranslationextendsModel' => 1,
        ],
        // Existing authorized DB content/mobile catalog management. ARB keys,
        // regional codes and export/import formats remain compatibility contracts.
        'app/Http/Controllers/Store/Admin/LanguageController.php' => [
            "table-name:'app_translations'" => 4,
            "table-name:'translations'" => 2,
            "query:DB::table('app_translations')->where('lang',\$language->app_lang_code)->orderBy('id')->pluck('lang_value','lang_key')->toJson(JSON_UNESCAPED_UNICODE)" => 1,
            "query:DB::table(\$table)->select(['id','lang_key','lang_value'])->where('lang','en')" => 1,
            "query:DB::table(\$table)->where('lang',\$app?\$language->app_lang_code:\$language->code)->whereIn('lang_key',\$rows->pluck('lang_key'))->orderBy('id')->pluck('lang_value','lang_key')" => 1,
            "query:DB::table(\$table)->updateOrInsert(['lang'=>\$lang,'lang_key'=>\$key],['lang_value'=>\$value,'updated_at'=>now(),])" => 1,
        ],
        'app/Http/Requests/Store/Admin/UpdateTranslationsRequest.php' => [
            "table-name:'app_translations'" => 1,
            "table-name:'translations'" => 1,
            "query:Rule::exists(Language::class,'id')" => 1,
            "query:Rule::exists(\$app?'app_translations':'translations','lang_key')->where('lang','en')" => 1,
        ],
    ];
    $actual = [];
    foreach (['app', 'resources', 'routes', 'config', 'bootstrap'] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            $events = localizationBoundaryEvents(file_get_contents($file->getPathname()), str_ends_with($path, '.blade.php'));
            if ($events !== []) {
                $actual[$path] = $events;
            }
        }
    }
    foreach ($allowed as &$events) {
        ksort($events);
    }
    unset($events);
    ksort($allowed);
    ksort($actual);

    expect($actual)->toBe($allowed);
});

test('the localization source boundary detects extra calls inside otherwise allowed files', function () {
    $source = <<<'PHP'
<?php
translate($value);
translate($value);
\translate('New static label');
TRANSLATE('Case-insensitive helper');
use function translate as legacy;
DB::table('translations')->where('lang', 'ar')->get();
DB::table($table)->count();
\App\Models\Translation::all();
DB::select('select * from app_translations');
PHP;

    expect(localizationBoundaryEvents($source))->toBe([
        'helper-alias:functiontranslateaslegacy' => 1,
        "helper:TRANSLATE('Case-insensitive helper')" => 1,
        "helper:\\translate('New static label')" => 1,
        'helper:translate($value)' => 2,
        'model:\\App\\Models\\Translation::all()' => 1,
        'query:DB::table($table)->count()' => 1,
        "query:DB::table('translations')->where('lang','ar')->get()" => 1,
        "sql:'select * from app_translations'" => 1,
        "table-name:'translations'" => 1,
    ]);
});

test('the localization source boundary ignores comments methods and SVG while inspecting Blade PHP', function () {
    $source = <<<'BLADE'
{{-- {{ translate('Retired label') }} --}}
<svg><g transform="translate(12 34)"></g></svg>
<script>canvas.translate(1, 2);</script>
@php
    // translate('Retired PHP label');
    $fail('validation.required')->translate();
    $example = "translate('Example text')";
@endphp
{{ translate($value) }}
BLADE;

    expect(localizationBoundaryEvents($source, true))->toBe(['helper:translate($value)' => 1]);
});
