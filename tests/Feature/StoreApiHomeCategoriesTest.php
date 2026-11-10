<?php

use App\Models\Category;

beforeEach(function () {
    // Sibling categories: the home list must hold the chosen one and nothing else.
    $this->chosen = Category::forceCreate(['name' => 'Chosen', 'slug' => 'chosen', 'level' => 0, 'order_level' => 1]);
    $this->other = Category::forceCreate(['name' => 'Not chosen', 'slug' => 'not-chosen', 'level' => 0, 'order_level' => 2]);
});

test('the home and top category lists are empty, not a server error, before the home settings are saved', function (string $path) {
    $this->getJson("/api/v1/store/$path")->assertOk()->assertExactJson(['data' => [], 'success' => true, 'status' => 200]);
})->with(['categories/home', 'categories/top']);

test('the home and top category lists hold only the categories chosen in the home settings', function (string $path) {
    storeSetting('home_categories', json_encode([(string) $this->chosen->id]));

    $names = collect($this->getJson("/api/v1/store/$path")->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Chosen']);
})->with(['categories/home', 'categories/top']);
