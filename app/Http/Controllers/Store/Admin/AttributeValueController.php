<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterAttributeValuesRequest;
use App\Http\Requests\Store\Admin\StoreAttributeValueRequest;
use App\Http\Requests\Store\Admin\UpdateAttributeValueRequest;
use App\Http\Resources\Store\AttributeResource;
use App\Http\Resources\Store\AttributeValueResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class AttributeValueController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_product_attribute_values', only: ['index']),
            new Middleware('permission:edit_product_attribute_value', only: ['store', 'edit', 'update']),
            new Middleware('permission:delete_product_attribute_value', only: ['destroy']),
        ];
    }

    public function index(FilterAttributeValuesRequest $request, Attribute $attribute): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Product/AttributeValues/Index', [
            'attribute' => new AttributeResource($attribute),
            'values' => $this->paginated(AttributeValueResource::collection(
                AttributeValue::query()->select(['id', 'attribute_id', 'value'])
                    ->where('attribute_id', $attribute->id)->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function store(StoreAttributeValueRequest $request): RedirectResponse
    {
        $value = AttributeValue::create([
            'attribute_id' => $request->validated('attribute_id'),
            'value' => ucfirst($request->validated('value')),
        ]);

        return redirect()->route('attributes.show', $value->attribute_id);
    }

    public function edit(AttributeValue $attributeValue): Response
    {
        return Inertia::render('Store/Admin/Product/AttributeValues/Edit', [
            'value' => new AttributeValueResource($attributeValue),
        ]);
    }

    public function update(UpdateAttributeValueRequest $request, AttributeValue $attributeValue): RedirectResponse
    {
        $attributeValue->attribute_id = $request->validated('attribute_id');
        $attributeValue->value = ucfirst($request->validated('value'));
        $attributeValue->save();

        return redirect()->route('attributes.show', $attributeValue->attribute_id);
    }

    public function destroy(Request $request, AttributeValue $attributeValue): RedirectResponse
    {
        $attributeId = $attributeValue->attribute_id;
        $attributeValue->delete();

        return $this->redirectPreservingQuery('attributes.show', $request, ['attribute' => $attributeId]);
    }
}
