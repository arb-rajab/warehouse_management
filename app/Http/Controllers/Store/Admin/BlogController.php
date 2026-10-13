<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterBlogsRequest;
use App\Http\Requests\Store\Admin\StoreBlogRequest;
use App\Http\Requests\Store\Admin\UpdateBlogRequest;
use App\Http\Requests\Store\Admin\UpdateBlogStatusRequest;
use App\Http\Resources\Store\BlogResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller implements HasMiddleware
{
    use ProvidesStoreUploadPreviews;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_blogs', only: ['index']),
            new Middleware('permission:add_blog', only: ['create', 'store']),
            new Middleware('permission:edit_blog', only: ['edit', 'update']),
            new Middleware('permission:delete_blog', only: ['destroy']),
            new Middleware('permission:publish_blog', only: ['changeStatus']),
        ];
    }

    public function index(FilterBlogsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $blogs = Blog::query()->select(['id', 'category_id', 'title', 'slug', 'status'])
            ->with('category:id,category_name')->orderByDesc('created_at')->orderByDesc('id');
        if ($request->filled('search')) {
            $blogs->where('title', 'like', '%'.$request->validated('search').'%');
        }

        return Inertia::render('Store/Admin/Blog/Posts/Index', [
            'blogs' => $this->paginated(BlogResource::collection($blogs->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->validated('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Blog/Posts/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreBlogRequest $request): RedirectResponse
    {
        $blog = new Blog;
        $this->write($blog, $request->validated());

        return redirect()->route('blog.index');
    }

    public function edit(Request $request, Blog $blog): Response
    {
        $blog = Blog::query()->select(['id', 'category_id', 'title', 'slug', 'short_description', 'description', 'banner', 'meta_title', 'meta_img', 'meta_description', 'meta_keywords', 'status'])
            ->with('category:id,category_name')->findOrFail($blog->id);

        return Inertia::render('Store/Admin/Blog/Posts/Edit', [
            'blog' => new BlogResource($blog),
            'categories' => $this->categoryOptions(),
            'initialFiles' => [
                'banner' => $this->uploadPreviews($request, $this->imageUpload($blog->banner)),
                'meta_img' => $this->uploadPreviews($request, $this->imageUpload($blog->meta_img)),
            ],
        ]);
    }

    public function update(UpdateBlogRequest $request, Blog $blog): RedirectResponse
    {
        $this->write($blog, $request->validated());

        return redirect()->route('blog.index');
    }

    public function destroy(Request $request, Blog $blog): RedirectResponse
    {
        $blog->delete();

        return $this->redirectPreservingQuery('blog.index', $request);
    }

    public function changeStatus(UpdateBlogStatusRequest $request, Blog $blog): RedirectResponse
    {
        $blog->status = $request->boolean('status') ? 1 : 0;
        $blog->save();

        return $this->redirectPreservingQuery('blog.index', $request);
    }

    /**
     * The categories a post may be filed under, in the order the Blade form
     * listed them (`BlogCategory::all()`); deleted categories are left out.
     *
     * @return list<array{id: int, category_name: string}>
     */
    private function categoryOptions(): array
    {
        return array_values(BlogCategory::query()->select(['id', 'category_name'])->orderBy('id')->get()
            ->map(fn (BlogCategory $category): array => ['id' => $category->id, 'category_name' => $category->category_name])
            ->all());
    }

    private function imageUpload(int|string|null $id): ?Upload
    {
        return $id === null ? null : Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($id);
    }

    /** @param array<string, mixed> $validated */
    private function write(Blog $blog, array $validated): void
    {
        foreach (['category_id', 'title', 'slug', 'short_description', 'banner', 'meta_title', 'meta_img', 'meta_description', 'meta_keywords'] as $field) {
            $blog->setAttribute($field, $validated[$field] ?? null);
        }
        $blog->description = clean_html($validated['description'] ?? null);
        $blog->save();
    }
}
