<?php

use App\Models\Page;
use App\Models\PageTranslation;

test('a page translates its fields and keeps unrelated translations out', function () {
    $page = Page::forceCreate(['title' => 'Base', 'slug' => 'about', 'content' => 'Base content', 'type' => 'custom_page']);
    $other = Page::forceCreate(['title' => 'Other', 'slug' => 'other', 'type' => 'custom_page']);
    $translation = PageTranslation::forceCreate(['page_id' => $page->id, 'lang' => 'ar', 'title' => 'Arabic', 'content' => 'Arabic content']);
    PageTranslation::forceCreate(['page_id' => $other->id, 'lang' => 'ar', 'title' => 'Noise', 'content' => 'Noise content']);
    expect($page->page_translations->modelKeys())->toBe([$translation->id])
        ->and($page->getTranslation('title', 'ar'))->toBe('Arabic')
        ->and($page->getTranslation('content', 'ar'))->toBe('Arabic content')
        ->and($page->getTranslation('title', 'en'))->toBe('Base');
    app()->setLocale('ar');
    expect($page->getTranslation('title'))->toBe('Arabic')
        ->and($page->getTranslation('title', null))->toBe('Arabic');
});
