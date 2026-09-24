<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAssignmentTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Create 'Uncategorized' category
        Category::factory()->create(['name' => 'Uncategorized']);

        // Create category with English name
        Category::factory()->create(['name' => 'Electronics']);

        // Create category with Arabic translation
        $arabicCategory = Category::factory()->create(['name' => 'Dummy']);
        $arabicCategory->category_translations()->create([
            'locale' => 'ar',
            'name' => 'إلكترونيات',
        ]);
    }

    public function test_it_assigns_category_by_en_name()
    {
        $product = [
            'enCatName' => 'Electronics',
            'arCatName' => 'أي شيء',
        ];

        $categoryId = $this->getCategoryIdFromProduct($product);

        $this->assertEquals(
            Category::where('name', 'Electronics')->first()->id,
            $categoryId
        );
    }

    public function test_it_assigns_category_by_ar_name_if_en_name_missing()
    {
        $product = [
            'enCatName' => null,
            'arCatName' => 'إلكترونيات',
        ];

        $categoryId = $this->getCategoryIdFromProduct($product);

        $this->assertEquals(
            Category::whereHas('category_translations', function ($q) {
                $q->where('name', 'إلكترونيات')->where('locale', 'ar');
            })->first()->id,
            $categoryId
        );
    }

    public function test_it_defaults_to_uncategorized_if_no_match()
    {
        $product = [
            'enCatName' => 'SomethingElse',
            'arCatName' => 'شيء آخر',
        ];

        $categoryId = $this->getCategoryIdFromProduct($product);

        $this->assertEquals(
            Category::where('name', 'Uncategorized')->first()->id,
            $categoryId
        );
    }

    private function getCategoryIdFromProduct($product)
    {
        if (
            !empty($product['enCatName']) &&
            $category = Category::where('name', $product['enCatName'])->first()
        ) {
            return $category->id;
        }

        if (
            !empty($product['arCatName']) &&
            $category = Category::whereHas('category_translations', function ($query) use ($product) {
                $query->where('name', $product['arCatName'])->where('locale', 'ar');
            })->first()
        ) {
            return $category->id;
        }

        return Category::where('name', 'Uncategorized')->first()->id;
    }
}
