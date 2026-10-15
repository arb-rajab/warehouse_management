<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterBlogCategoriesRequest;
use App\Http\Requests\Store\Admin\StoreBlogCategoryRequest;
use App\Http\Requests\Store\Admin\UpdateBlogCategoryRequest;
use App\Http\Resources\Store\BlogCategoryResource;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class BlogCategoryController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_blog_categories', only: ['index']),
            new Middleware('permission:add_blog_category', only: ['create', 'store']),
            new Middleware('permission:edit_blog_category', only: ['edit', 'update']),
            new Middleware('permission:delete_blog_category', only: ['destroy']),
        ];
    }

    public function index(FilterBlogCategoriesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Blog/Categories/Index', [
            'categories' => $this->paginated(BlogCategoryResource::collection(
                BlogCategory::query()->select(['id', 'category_name'])
                    ->when($search, fn ($query) => $query->where('category_name', 'like', '%'.$search.'%'))
                    ->orderBy('category_name')->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage, 'search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Blog/Categories/Create');
    }

    public function store(StoreBlogCategoryRequest $request): RedirectResponse
    {
        $category = new BlogCategory;
        $category->category_name = $request->validated('category_name');
        $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $category->category_name));
        $category->save();

        return redirect()->route('blog-category.index');
    }

    public function edit(BlogCategory $category): Response
    {
        return Inertia::render('Store/Admin/Blog/Categories/Edit', [
            'category' => new BlogCategoryResource($category),
        ]);
    }

    public function update(UpdateBlogCategoryRequest $request, BlogCategory $category): RedirectResponse
    {
        $category->category_name = $request->validated('category_name');
        $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $category->category_name));
        $category->save();

        return redirect()->route('blog-category.index');
    }

    public function destroy(Request $request, BlogCategory $category): RedirectResponse
    {
        $category->delete();

        return $this->redirectPreservingQuery('blog-category.index', $request);
    }
}
