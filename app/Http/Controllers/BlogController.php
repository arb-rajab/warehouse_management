<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;

/**
 * The public blog. Its back-office actions live in
 * Store\Admin\BlogController (store-backoffice-migration.md §18).
 */
class BlogController extends Controller
{
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
        $blog = Blog::with('category:id,category_name')->where('slug', $slug)->where('status', 1)->firstOrFail();
        $recent_blogs = Blog::with('category:id,category_name')->where('status', 1)->orderBy('created_at', 'desc')->limit(9)->get();
        return view("shop.frontend.blog.details", compact('blog', 'recent_blogs'));
    }
}
