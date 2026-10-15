<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterPickupPointsRequest;
use App\Http\Requests\Store\Admin\StorePickupPointRequest;
use App\Http\Requests\Store\Admin\UpdatePickupPointRequest;
use App\Http\Resources\Store\PickupPointResource;
use App\Models\PickupPoint;
use App\Models\PickupPointTranslation;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PickupPointController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:pickup_point_setup')];
    }

    public function index(FilterPickupPointsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = PickupPoint::query()->select(['id', 'name', 'address', 'phone', 'staff_id', 'pick_up_status'])->with(['pickup_point_translations:id,pickup_point_id,lang,name,address', 'staff:id,user_id', 'staff.user:id,name']);
        $query->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('search').'%'));

        return Inertia::render('Store/Admin/Setup/PickupPoints/Index', [
            'pickupPoints' => $this->paginated(PickupPointResource::collection($query->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->validated('search')],
        ]);
    }

    /** @return array<int, array{id: int, name: string}> */
    private function staffOptions(): array
    {
        return Staff::query()->select(['id', 'user_id'])->has('user')->with('user:id,name')->orderBy('id')->get()->map(fn (Staff $staff) => ['id' => $staff->id, 'name' => $staff->user->name])->all();
    }

    public function edit(EditReferenceTranslationRequest $request, PickupPoint $pickupPoint): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $pickupPoint->load(['pickup_point_translations:id,pickup_point_id,lang,name,address', 'staff:id,user_id', 'staff.user:id,name']);
        $translation = $pickupPoint->pickup_point_translations->firstWhere('lang', $lang);

        return Inertia::render('Store/Admin/Setup/PickupPoints/Edit', [
            'pickupPoint' => new PickupPointResource($pickupPoint),
            'lang' => $lang, 'languages' => $this->languageOptions(), 'name' => $translation->name ?? $pickupPoint->name, 'address' => $translation->address ?? $pickupPoint->address,
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Setup/PickupPoints/Create', [
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function store(StorePickupPointRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $pickupPoint = new PickupPoint;
            $pickupPoint->name = $request->validated('name');
            $pickupPoint->address = $request->validated('address');
            $pickupPoint->phone = $request->validated('phone');
            $pickupPoint->staff_id = $request->validated('staff_id');
            $pickupPoint->pick_up_status = $request->validated('pick_up_status');
            $pickupPoint->save();
            PickupPointTranslation::query()->updateOrCreate(['pickup_point_id' => $pickupPoint->id, 'lang' => config('app.default_language')], $request->safe()->only(['name', 'address']));
        });

        return redirect()->route('pick_up_points.index');
    }

    public function update(UpdatePickupPointRequest $request, PickupPoint $pickupPoint): RedirectResponse
    {
        DB::transaction(function () use ($request, $pickupPoint) {
            if ($request->validated('lang') === config('app.default_language')) {
                $pickupPoint->name = $request->validated('name');
                $pickupPoint->address = $request->validated('address');
            }
            $pickupPoint->phone = $request->validated('phone');
            $pickupPoint->staff_id = $request->validated('staff_id');
            $pickupPoint->pick_up_status = $request->validated('pick_up_status');
            $pickupPoint->save();
            PickupPointTranslation::query()->updateOrCreate(['pickup_point_id' => $pickupPoint->id, 'lang' => $request->validated('lang')], $request->safe()->only(['name', 'address']));
        });

        return redirect()->route('pick_up_points.index');
    }

    public function destroy(Request $request, PickupPoint $pickupPoint): RedirectResponse
    {
        DB::transaction(function () use ($pickupPoint) {
            $pickupPoint->pickup_point_translations()->delete();
            $pickupPoint->delete();
        });

        return $this->redirectPreservingQuery('pick_up_points.index', $request);
    }
}
