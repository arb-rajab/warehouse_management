<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditTranslatedResourceRequest;
use App\Http\Requests\Store\Admin\FilterPickupPointsRequest;
use App\Http\Requests\Store\Admin\StorePickupPointRequest;
use App\Http\Requests\Store\Admin\UpdatePickupPointRequest;
use App\Http\Resources\Store\PickupPointResource;
use App\Models\Language;
use App\Models\PickupPoint;
use App\Models\PickupPointTranslation;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PickupPointController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:pickup_point_setup')];
    }

    public function index(FilterPickupPointsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Setup/PickupPoints/Index', [
            'pickupPoints' => $this->paginated(PickupPointResource::collection(
                PickupPoint::query()
                    ->select(['id', 'staff_id', 'name', 'address', 'phone', 'pick_up_status'])
                    ->with($this->relations())
                    ->when($search, fn ($query, string $value) => $query->where('name', 'like', "%{$value}%"))
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
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
            $this->fill($pickupPoint, $request);
            $pickupPoint->name = $request->validated('name');
            $pickupPoint->address = $request->validated('address');
            $pickupPoint->save();

            PickupPointTranslation::query()->create([
                'pickup_point_id' => $pickupPoint->id,
                'lang' => config('app.default_language'),
                'name' => $request->validated('name'),
                'address' => $request->validated('address'),
            ]);
        });

        return redirect()->route('pick_up_points.index');
    }

    public function edit(EditTranslatedResourceRequest $request, PickupPoint $pickupPoint): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $pickupPoint->load($this->relations());
        $translation = $pickupPoint->pickup_point_translations
            ->filter(fn (PickupPointTranslation $item): bool => $item->lang === $lang)
            ->first();

        return Inertia::render('Store/Admin/Setup/PickupPoints/Edit', [
            'pickupPoint' => new PickupPointResource($pickupPoint),
            'lang' => $lang,
            'translatedName' => data_get($translation, 'name', $pickupPoint->name),
            'translatedAddress' => data_get($translation, 'address', $pickupPoint->address),
            'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function update(UpdatePickupPointRequest $request, PickupPoint $pickupPoint): RedirectResponse
    {
        DB::transaction(function () use ($request, $pickupPoint) {
            $lang = $request->validated('lang');

            if ($lang === config('app.default_language')) {
                $pickupPoint->name = $request->validated('name');
                $pickupPoint->address = $request->validated('address');
            }

            $this->fill($pickupPoint, $request);
            $pickupPoint->save();

            PickupPointTranslation::query()->updateOrCreate(
                ['pickup_point_id' => $pickupPoint->id, 'lang' => $lang],
                ['name' => $request->validated('name'), 'address' => $request->validated('address')],
            );
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

    /**
     * @return array<int|string, mixed>
     */
    private function relations(): array
    {
        return [
            'pickup_point_translations:id,pickup_point_id,name,address,lang',
            'staff:id,user_id',
            'staff.user' => fn (Relation $query) => $query->select(['id', 'name']),
        ];
    }

    /**
     * @return list<array{id: int, name: string|null}>
     */
    private function staffOptions(): array
    {
        return array_values(Staff::query()
            ->select(['id', 'user_id'])
            ->has('user')
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->toBase()
            ->map(fn (Staff $staff): array => ['id' => $staff->id, 'name' => $staff->user?->name])
            ->values()
            ->all());
    }

    private function fill(PickupPoint $pickupPoint, StorePickupPointRequest|UpdatePickupPointRequest $request): void
    {
        $pickupPoint->phone = $request->validated('phone');
        $pickupPoint->pick_up_status = $request->boolean('pick_up_status') ? 1 : 0;
        $pickupPoint->staff_id = $request->integer('staff_id');
    }
}
