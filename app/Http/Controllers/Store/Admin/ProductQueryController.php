<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterProductQueriesRequest;
use App\Http\Requests\Store\Admin\ReplyProductQueryRequest;
use App\Http\Resources\Store\ProductQueryResource;
use App\Models\ProductQuery;
use App\Models\StoreUser;
use App\Policies\ProductQueryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ProductQueryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ProductQueryPolicy $policy) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_product_queries', only: ['index', 'show']),
            new Middleware('permission:reply_to_product_queries', only: ['reply']),
        ];
    }

    public function index(FilterProductQueriesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Support/ProductQueries/Index', [
            'queries' => $this->paginated(ProductQueryResource::collection(
                ProductQuery::query()
                    ->select(['id', 'customer_id', 'product_id', 'question', 'reply', 'created_at'])
                    ->where('seller_id', StoreUser::query()->where('user_type', 'admin')->orderBy('id')->value('id'))
                    ->with($this->relations())
                    ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function show(Request $request, ProductQuery $productQuery): Response
    {
        $productQuery->load($this->relations());

        return Inertia::render('Store/Admin/Support/ProductQueries/Show', [
            'query' => new ProductQueryResource($productQuery),
            'canReply' => $this->policy->reply($request->user('store'), $productQuery),
        ]);
    }

    public function reply(ReplyProductQueryRequest $request, ProductQuery $productQuery): RedirectResponse
    {
        abort_unless($this->policy->reply($request->user('store'), $productQuery), 403);
        $productQuery->reply = clean_html($request->validated('reply'));
        $productQuery->save();

        return redirect()->route('product_query.index');
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['user:id,name', 'product:id,name', 'product.product_translations:id,product_id,lang,name'];
    }
}
