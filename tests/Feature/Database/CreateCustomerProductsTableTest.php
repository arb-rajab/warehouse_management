<?php

use Illuminate\Support\Facades\Schema;

test('customer_products table has the expected columns', function () {
    expect(Schema::hasTable('customer_products'))->toBeTrue();
    expect(Schema::hasColumns('customer_products', [
        'id', 'name', 'published', 'status', 'added_by', 'user_id', 'category_id',
        'subcategory_id', 'subsubcategory_id', 'brand_id', 'photos', 'thumbnail_img',
        'conditon', 'location', 'video_provider', 'video_link', 'unit', 'tags',
        'description', 'unit_price', 'meta_title', 'meta_description', 'meta_img',
        'pdf', 'slug', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
