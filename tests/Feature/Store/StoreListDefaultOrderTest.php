<?php

use Illuminate\Support\Facades\File;

/*
 * A store back office list controller must declare its default order: either it
 * applies App\Support\NewestFirst, or it is named as an exception (with the
 * reason) in .ai/rules/store-list-default-order.md and below. A new controller
 * that paginates without doing either fails here.
 */

/** Controllers that paginate but are deliberately not newest first: file => why. */
const STORE_LIST_ORDER_EXCEPTIONS = [
    'CategoryController' => 'ordered by the display priority (order_level) staff maintain',
];

/** Lists inside controllers that otherwise use the helper: file => what stays as it is. */
const STORE_LIST_ORDER_PARTIAL_EXCEPTIONS = [
    'ReportController' => 'in-house sales and search reports rank by a metric',
    'CustomerController' => 'the page-view list ranks by view count',
    'LanguageController' => 'translation keys keep their catalogue order',
    'ConversationController' => 'a message thread reads oldest to newest',
    'SupportTicketController' => 'ticket replies are a thread with its own newest-first id order',
    'UploadController' => 'the file picker has its own sort control, newest by default',
];

function storeAdminListControllers(): array
{
    $controllers = [];
    foreach (File::files(app_path('Http/Controllers/Store/Admin')) as $file) {
        $controllers[$file->getBasename('.php')] = $file->getContents();
    }

    return $controllers;
}

test('every store back office list controller applies newest first or is a listed exception', function () {
    $undeclared = [];
    foreach (storeAdminListControllers() as $name => $source) {
        if (! str_contains($source, 'paginate(')) {
            continue;
        }
        if (! str_contains($source, 'NewestFirst::apply(') && ! array_key_exists($name, STORE_LIST_ORDER_EXCEPTIONS) && ! array_key_exists($name, STORE_LIST_ORDER_PARTIAL_EXCEPTIONS)) {
            $undeclared[] = $name;
        }
    }

    expect($undeclared)->toBe([], 'Apply NewestFirst::apply(...) to the list query, or record the exception in .ai/rules/store-list-default-order.md and this test.');
});

test('the listed exceptions are real and every one is explained in the rule', function () {
    $controllers = storeAdminListControllers();
    $rule = File::get(base_path('.ai/rules/store-list-default-order.md'));

    foreach (STORE_LIST_ORDER_EXCEPTIONS as $name => $reason) {
        expect($controllers)->toHaveKey($name);
        expect($controllers[$name])->toContain('paginate(')->not->toContain('NewestFirst::apply(');
        expect($rule)->toContain($name);
    }
    foreach (STORE_LIST_ORDER_PARTIAL_EXCEPTIONS as $name => $reason) {
        expect($controllers)->toHaveKey($name);
        expect($controllers[$name])->toContain('paginate(');
        expect($rule)->toContain($name);
    }
});

test('typeahead controllers use the shared SearchesOptions order and are named in the rule', function () {
    $rule = File::get(base_path('.ai/rules/store-list-default-order.md'));
    $typeahead = array_keys(array_filter(storeAdminListControllers(), fn (string $source) => str_contains($source, 'use SearchesOptions;')));

    expect($typeahead)->toContain('OrderEntryController');
    expect($rule)->toContain('SearchesOptions');
    foreach ($typeahead as $name) {
        expect($rule)->toContain($name);
    }
    // The shared trait is where the typeahead order lives: prefix matches, then name, then key.
    expect(File::get(app_path('Http/Controllers/Store/Admin/Concerns/SearchesOptions.php')))->toContain('function orderByRelevance');
});
