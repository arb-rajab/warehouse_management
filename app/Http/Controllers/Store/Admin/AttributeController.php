<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditAttributeRequest;
use App\Http\Requests\Store\Admin\FilterAttributesRequest;
use App\Http\Requests\Store\Admin\StoreAttributeRequest;
use App\Http\Requests\Store\Admin\UpdateAttributeRequest;
use App\Http\Resources\Store\AttributeResource;
use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_product_attributes', only: ['index']),
            new Middleware('permission:edit_product_attribute', only: ['store', 'edit', 'update']),
            new Middleware('permission:delete_product_attribute', only: ['destroy']),
        ];
    }

    public function index(FilterAttributesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Product/Attributes/Index', [
            'attributes' => $this->paginated(AttributeResource::collection(
                Attribute::query()->select(['id', 'name'])
                    ->with(['attribute_translations:id,attribute_id,lang,name', 'attribute_values:id,attribute_id,value'])
                    ->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function store(StoreAttributeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $attribute = Attribute::create(['name' => $request->validated('name')]);
            AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => config('app.default_language'), 'name' => $request->validated('name')]);
        });

        return redirect()->route('attributes.index');
    }

    public function edit(EditAttributeRequest $request, Attribute $attribute): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');

        return Inertia::render('Store/Admin/Product/Attributes/Edit', [
            'attribute' => new AttributeResource($attribute),
            'lang' => $lang,
            'name' => $attribute->getTranslation('name', $lang),
            'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        $lang = $request->validated('lang');
        DB::transaction(function () use ($request, $attribute, $lang) {
            if ($lang === config('app.default_language')) {
                $attribute->name = $request->validated('name');
                $attribute->save();
            }
            AttributeTranslation::query()->updateOrCreate(['attribute_id' => $attribute->id, 'lang' => $lang], ['name' => $request->validated('name')]);
        });

        return redirect()->route('attributes.index');
    }

    public function destroy(Request $request, Attribute $attribute): RedirectResponse
    {
        DB::transaction(function () use ($attribute) {
            AttributeTranslation::query()->where('attribute_id', $attribute->id)->delete();
            $attribute->delete();
        });

        return $this->redirectPreservingQuery('attributes.index', $request);
    }
}
