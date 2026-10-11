<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterTaxesRequest;
use App\Http\Requests\Store\Admin\StoreTaxRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Requests\Store\Admin\UpdateTaxRequest;
use App\Http\Resources\Store\TaxResource;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:vat_&_tax_setup')];
    }

    public function index(FilterTaxesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Setup/Taxes/Index', [
            'taxes' => $this->paginated(TaxResource::collection(
                Tax::query()
                    ->select(['id', 'name', 'tax_status'])
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function store(StoreTaxRequest $request): RedirectResponse
    {
        $tax = new Tax;
        $tax->name = $request->validated('name');
        $tax->save();

        return redirect()->route('tax.index');
    }

    public function edit(Tax $tax): Response
    {
        return Inertia::render('Store/Admin/Setup/Taxes/Edit', ['tax' => new TaxResource($tax)]);
    }

    public function update(UpdateTaxRequest $request, Tax $tax): RedirectResponse
    {
        $tax->name = $request->validated('name');
        $tax->save();

        return redirect()->route('tax.index');
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, Tax $tax): RedirectResponse
    {
        $tax->tax_status = $request->boolean('status');
        $tax->save();

        return $this->redirectPreservingQuery('tax.index', $request);
    }

    public function destroy(Request $request, Tax $tax): RedirectResponse
    {
        $tax->delete();

        return $this->redirectPreservingQuery('tax.index', $request);
    }
}
