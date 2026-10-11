<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BlogController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_blogs', only: ['index', 'show']),
            new Middleware('permission:add_blog', only: ['create', 'store']),
            new Middleware('permission:edit_blog', only: ['edit', 'update']),
            new Middleware('permission:delete_blog', only: ['destroy']),
            new Middleware('permission:publish_blog', only: ['change_status']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(): \Illuminate\Contracts\View\View
    {
        $blog_categories = BlogCategory::all();
        return view('shop.backend.blog_system.blog.create', compact('blog_categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {

        $request->validate([
            'category_id' => 'required',
            'title' => 'required|max:255',
        ]);

        $blog = new Blog;

        $blog->category_id = $request->category_id;
        $blog->title = $request->title;
        $blog->banner = $request->banner;
        $blog->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->slug));
        $blog->short_description = $request->short_description;
        $blog->description = $request->description;

        $blog->meta_title = $request->meta_title;
        $blog->meta_img = $request->meta_img;
        $blog->meta_description = $request->meta_description;
        $blog->meta_keywords = $request->meta_keywords;

        $blog->save();

        flash(translate('Blog post has been created successfully'))->success();
        return redirect()->route('blog.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id): \Illuminate\Contracts\View\View
    {
        $blog = Blog::find($id);
        $blog_categories = BlogCategory::all();

        return view('shop.backend.blog_system.blog.edit', compact('blog','blog_categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required',
            'title' => 'required|max:255',
        ]);

        $blog = Blog::find($id);

        $blog->category_id = $request->category_id;
        $blog->title = $request->title;
        $blog->banner = $request->banner;
        $blog->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->slug));
        $blog->short_description = $request->short_description;
        $blog->description = $request->description;

        $blog->meta_title = $request->meta_title;
        $blog->meta_img = $request->meta_img;
        $blog->meta_description = $request->meta_description;
        $blog->meta_keywords = $request->meta_keywords;

        $blog->save();

        flash(translate('Blog post has been updated successfully'))->success();
        return redirect()->route('blog.index');
    }

    public function change_status(Request $request) {
        $blog = Blog::find($request->id);
        $blog->status = $request->status;

        $blog->save();
        return 1;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        Blog::find($id)->delete();

        return redirect('admin/blogs');
    }


    public function all_blog(Request $request) {
        $selected_categories = [];
        $search = null;
        $blogs = Blog::query()->with('category:id,category_name');

        if ($request->has('search')) {
            $search = $request->search;;
            $blogs->where(function ($q) use ($search) {
                foreach (explode(' ', trim($search)) as $word) {
                    $q->where('title', 'like', '%' . $word . '%')
                        ->orWhere('short_description', 'like', '%' . $word . '%');
                }
            });

            $case1 = $search . '%';
            $case2 = '%' . $search . '%';

            $blogs->orderByRaw("CASE
                WHEN title LIKE ? THEN 1
                WHEN title LIKE ? THEN 2
                ELSE 3
                END", [$case1, $case2]);
        }

        if ($request->has('selected_categories')) {
            $selected_categories = $request->selected_categories;
            $blog_categories = BlogCategory::whereIn('slug', $selected_categories)->pluck('id')->toArray();

            $blogs->whereIn('category_id', $blog_categories);
        }

        $blogs = $blogs->where('status', 1)->orderBy('created_at', 'desc')->paginate(12);

        $recent_blogs = Blog::with('category:id,category_name')->where('status', 1)->orderBy('created_at', 'desc')->limit(9)->get();

        return view("shop.frontend.blog.listing", compact('blogs', 'selected_categories', 'search', 'recent_blogs'));
    }

    public function blog_details($slug) {
        $blog = Blog::with('category:id,category_name')->where('slug', $slug)->first();
        $recent_blogs = Blog::with('category:id,category_name')->where('status', 1)->orderBy('created_at', 'desc')->limit(9)->get();
        return view("shop.frontend.blog.details", compact('blog', 'recent_blogs'));
    }
}
