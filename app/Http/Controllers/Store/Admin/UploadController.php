<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterUploadsRequest;
use App\Http\Requests\Store\Admin\StoreUploadRequest;
use App\Http\Requests\Store\Admin\UploadIdsRequest;
use App\Http\Resources\Store\UploadResource;
use App\Models\StoreUser;
use App\Models\Upload;
use App\Services\MultipartUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store back office's uploaded files (/temp/admin/uploaded-files): the
 * library page, the upload page, and the JSON endpoints behind the shared
 * file manager dialog (components/StoreFileManagerDialog.vue).
 *
 * No permission gates these, as none gated the Blade screens: every back
 * office account (`auth` + `admin`) may open the library and upload.
 * UploadPolicy decides which files an account may preview or delete: admin
 * and staff any, an accountant their own.
 */
class UploadController extends Controller
{
    /** The columns UploadResource reads, plus `user_id` for the uploader. */
    private const COLUMNS = ['id', 'user_id', 'file_original_name', 'file_name', 'external_link', 'extension', 'type', 'file_size', 'created_at'];

    /** The file manager dialog's page size, the Blade uploader's. */
    private const BROWSE_PER_PAGE = 60;

    public function index(FilterUploadsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/UploadedFiles/Index', [
            'uploads' => $this->paginated(UploadResource::collection(
                $this->filtered(Upload::query(), $request)
                    ->with('user:id,name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => [
                'search' => $request->validated('search'),
                'sort' => $request->validated('sort') ?? 'newest',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/UploadedFiles/Create');
    }

    /**
     * The file manager dialog's grid: the signed-in account's own files, as
     * the Blade uploader listed them.
     */
    public function browse(FilterUploadsRequest $request): AnonymousResourceCollection
    {
        return UploadResource::collection(
            $this->filtered(Upload::query()->where('user_id', $this->storeUser($request)->id), $request)
                ->paginate(self::BROWSE_PER_PAGE)
                ->withQueryString()
        );
    }

    /**
     * The files an upload field holds, in the order it holds them, leaving
     * out any the account may not see.
     */
    public function previews(UploadIdsRequest $request): AnonymousResourceCollection
    {
        $ids = $request->ids();
        $user = $this->storeUser($request);

        $uploads = Upload::query()
            ->select(self::COLUMNS)
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (Upload $upload): bool => $user->can('view', $upload))
            ->sortBy(fn (Upload $upload): int|false => array_search($upload->id, $ids, true))
            ->values();

        return UploadResource::collection($uploads);
    }

    public function store(StoreUploadRequest $request, MultipartUploadService $uploads): JsonResponse
    {
        $file = $request->file('file');
        $upload = $file instanceof UploadedFile && ! $this->refusedInDemo($file)
            ? $uploads->store($file, $this->storeUser($request)->id)
            : null;

        if ($upload === null) {
            throw ValidationException::withMessages(['file' => __('messages.upload_refused')]);
        }

        return (new UploadResource($upload))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Upload $upload, MultipartUploadService $uploads): RedirectResponse
    {
        Gate::forUser($this->storeUser($request))->authorize('delete', $upload);

        $uploads->delete($upload);

        return $this->redirectPreservingQuery('uploaded-files.index', $request);
    }

    /**
     * Delete the ticked files. A file the account may not delete is skipped,
     * as the Blade bulk delete skipped it.
     */
    public function bulkDestroy(UploadIdsRequest $request, MultipartUploadService $uploads): RedirectResponse
    {
        $user = $this->storeUser($request);

        Upload::query()
            ->select(['id', 'user_id', 'file_name'])
            ->whereIn('id', $request->ids())
            ->get()
            ->filter(fn (Upload $upload): bool => $user->can('delete', $upload))
            ->each(fn (Upload $upload) => $uploads->delete($upload));

        return $this->redirectPreservingQuery('uploaded-files.index', $request);
    }

    /**
     * The search and sort the library and the dialog share.
     *
     * @param  Builder<Upload>  $query
     * @return Builder<Upload>
     */
    private function filtered(Builder $query, FilterUploadsRequest $request): Builder
    {
        $search = $request->validated('search');
        $type = $request->validated('type');

        $query->select(self::COLUMNS)
            ->when(is_string($search) && $search !== '', fn (Builder $query) => $query->originalNameContains((string) $search))
            ->when(is_string($type), fn (Builder $query) => $query->where('type', $type));

        return match ($request->validated('sort')) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'smallest' => $query->orderBy('file_size')->orderBy('id'),
            'largest' => $query->orderByDesc('file_size')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * The demo shop takes no archives, as the Blade uploader took none.
     */
    private function refusedInDemo(UploadedFile $file): bool
    {
        return config('store.demo_mode')
            && (MultipartUploadService::KINDS[strtolower($file->getClientOriginalExtension())] ?? null) === 'archive';
    }

    private function storeUser(Request $request): StoreUser
    {
        $user = $request->user('store');
        abort_unless($user instanceof StoreUser, 403);

        return $user;
    }
}
