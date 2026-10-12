<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\DeleteUploadsRequest;
use App\Http\Requests\Store\Admin\FilterUploadsRequest;
use App\Http\Requests\Store\Admin\PreviewUploadsRequest;
use App\Http\Requests\Store\Admin\StoreUploadRequest;
use App\Http\Resources\Store\UploadResource;
use App\Models\Upload;
use App\Services\MultipartUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UploadController extends Controller implements HasMiddleware
{
    /** @return list<Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_uploads', only: ['index', 'files', 'previews']),
            new Middleware('permission:upload_files', only: ['create', 'store']),
            new Middleware('permission:delete_uploads', only: ['destroy', 'bulkDestroy']),
            new Middleware('permission:delete_all_uploads', only: ['destroyAll']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Store/Admin/Uploads/Index');
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Uploads/Create');
    }

    public function files(FilterUploadsRequest $request): JsonResponse
    {
        $query = $this->uploadsQuery();
        if (! Gate::allows('viewAny', Upload::class)) {
            $query->where('user_id', $request->user('store')->id);
        }
        if ($request->filled('search')) {
            $query->originalNameContains($request->string('search')->toString());
        }
        if ($request->filled('type')) {
            $query->where('type', $request->validated('type'));
        }
        [$column, $direction] = match ($request->input('sort', 'newest')) {
            'oldest' => ['created_at', 'asc'],
            'smallest' => ['file_size', 'asc'],
            'largest' => ['file_size', 'desc'],
            default => ['created_at', 'desc'],
        };

        return response()->json($this->paginated(UploadResource::collection(
            $query->orderBy($column, $direction)->orderBy('id', $direction)
                ->paginate($this->resolvePerPage($request, 20))->withQueryString()
        )));
    }

    public function previews(PreviewUploadsRequest $request): JsonResponse
    {
        $ids = $request->array('ids');
        $files = $this->uploadsQuery()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($files as $file) {
            Gate::authorize('view', $file);
        }

        return response()->json(UploadResource::collection(collect($ids)->map(fn ($id) => $files->get($id))->filter()->values()));
    }

    public function store(StoreUploadRequest $request, MultipartUploadService $uploads): JsonResponse
    {
        abort_if(config('store.demo_mode'), 403);
        $upload = $uploads->store($request->file('file'), $request->user('store')->id, normalizeRaster: true);
        if ($upload === null) {
            throw ValidationException::withMessages(['file' => __('messages.upload_rejected')]);
        }

        return response()->json(new UploadResource($upload), 201);
    }

    public function destroy(Request $request, Upload $upload, MultipartUploadService $uploads): JsonResponse
    {
        abort_if(config('store.demo_mode'), 403);
        Gate::authorize('delete', $upload);
        $uploads->delete($upload);

        return response()->json(['deleted' => $upload->id]);
    }

    public function bulkDestroy(DeleteUploadsRequest $request, MultipartUploadService $uploads): JsonResponse
    {
        abort_if(config('store.demo_mode'), 403);
        $files = $this->uploadsQuery()->whereIn('id', $request->validated('id'))->get();
        foreach ($files as $file) {
            Gate::authorize('delete', $file);
        }
        foreach ($files as $file) {
            $uploads->delete($file);
        }

        return response()->json(['deleted' => $files->modelKeys()]);
    }

    public function destroyAll(Request $request, MultipartUploadService $uploads): RedirectResponse
    {
        abort_if(config('store.demo_mode'), 403);
        $this->uploadsQuery()->chunkById(100, function ($files) use ($uploads): void {
            foreach ($files as $file) {
                $uploads->delete($file);
            }
        });

        return $this->redirectPreservingQuery('uploaded-files.index', $request);
    }

    /** @return Builder<Upload> */
    private function uploadsQuery(): Builder
    {
        return Upload::query()->select(['id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link', 'user_id']);
    }
}
