<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Upload;
use App\Services\MultipartUploadService;
use Auth;
use Illuminate\Http\Request;
use Response;
use Storage;

class AizUploadController extends Controller
{
    public function index(Request $request)
    {

        $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('user_id', auth()->user()->id) : Upload::query();
        $search = null;
        $sort_by = null;

        if (is_string($request->search) && $request->search !== '') {
            $search = $request->search;
            $all_uploads->originalNameContains($request->search);
        }

        $sort_by = $request->sort;
        switch ($request->sort) {
            case 'newest':
                $all_uploads->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $all_uploads->orderBy('created_at', 'asc');
                break;
            case 'smallest':
                $all_uploads->orderBy('file_size', 'asc');
                break;
            case 'largest':
                $all_uploads->orderBy('file_size', 'desc');
                break;
            default:
                $all_uploads->orderBy('created_at', 'desc');
                break;
        }

        $all_uploads = $all_uploads->paginate(60)->appends(request()->query());


        return (auth()->user()->user_type == 'seller')
            ? view('shop.seller.uploads.index', compact('all_uploads', 'search', 'sort_by'))
            : view('shop.backend.uploaded_files.index', compact('all_uploads', 'search', 'sort_by'));
    }

    public function upload(Request $request, MultipartUploadService $uploads)
    {
        if ($request->hasFile('aiz_file')) {
            $extension = strtolower($request->file('aiz_file')->getClientOriginalExtension());

            if (
                env('DEMO_MODE') == 'On' &&
                (MultipartUploadService::KINDS[$extension] ?? null) == 'archive'
            ) {
                return $this->failed(__('api.upload.inserted'));
            }

            $upload = $uploads->store($request->file('aiz_file'), Auth::user()->id);

            if ($upload === null) {
                return response()->json(['result' => false, 'message' => __('storefront.upload.type_not_allowed')], 422);
            }

            return $this->success(__('api.upload.inserted'));
        }
    }

    public function get_uploaded_files(Request $request)
    {
        $uploads = Upload::where('user_id', Auth::user()->id);
        if (is_string($request->search) && $request->search !== '') {
            $uploads->originalNameContains($request->search);
        }
        if ($request->sort != null) {
            switch ($request->sort) {
                case 'newest':
                    $uploads->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $uploads->orderBy('created_at', 'asc');
                    break;
                case 'smallest':
                    $uploads->orderBy('file_size', 'asc');
                    break;
                case 'largest':
                    $uploads->orderBy('file_size', 'desc');
                    break;
                default:
                    $uploads->orderBy('created_at', 'desc');
                    break;
            }
        }
        return $uploads->paginate(60)->appends(request()->query());
    }

    public function destroy($id)
    {
        $upload = Upload::findOrFail($id);

        if (auth()->user()->user_type == 'seller' && $upload->user_id != auth()->user()->id) {
            flash(__('storefront.upload.delete_forbidden'))->error();
            return back();
        }
        try {
            if (config('filesystems.default') == 's3') {
                Storage::disk('s3')->delete($upload->file_name);
                if (file_exists(public_path() . '/' . $upload->file_name)) {
                    unlink(public_path() . '/' . $upload->file_name);
                }
            } else {
                unlink(public_path() . '/' . $upload->file_name);
            }
            $upload->delete();
            flash(__('storefront.upload.deleted'))->success();
        } catch (\Exception $e) {
            $upload->delete();
            flash(__('storefront.upload.deleted'))->success();
        }
        return back();
    }

    public function bulk_uploaded_files_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $file_id) {
                $this->destroy($file_id);
            }
            return 1;
        } else {
            return 0;
        }
    }

    public function get_preview_files(Request $request)
    {
        $ids = explode(',', $request->ids);
        $files = Upload::whereIn('id', $ids)->get();
        $new_file_array = [];
        foreach ($files as $file) {
            $file['file_name'] = my_asset($file->file_name);
            if ($file->external_link) {
                $file['file_name'] = $file->external_link;
            }
            $new_file_array[] = $file;
        }
        // dd($new_file_array);
        return $new_file_array;
        // return $files;
    }

    //Download project attachment
    public function attachment_download($id)
    {
        $project_attachment = Upload::findOrFail($id);
        try {
            $file_path = public_path($project_attachment->file_name);
            return Response::download($file_path);
        } catch (\Exception $e) {
            flash(__('storefront.upload.not_found'))->error();
            return back();
        }
    }
    //Download project attachment
    public function file_info(Request $request)
    {
        $file = Upload::findOrFail($request['id']);

        return (auth()->user()->user_type == 'seller')
            ? view('shop.seller.uploads.info', compact('file'))
            : view('shop.backend.uploaded_files.info', compact('file'));
    }
}
