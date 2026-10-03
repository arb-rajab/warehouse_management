<?php

namespace App\Http\Controllers\Api\V2\Seller;

use App\Http\Resources\V2\UploadedFileCollection;
use App\Models\Upload;
use App\Services\MultipartUploadService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Response;
use Storage;

class SellerFileUploadController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->user()->user_type == 'seller') {
            $all_uploads = Upload::where('user_id', auth()->user()->id);

            if ($request->search != null) {
                $escaped = str_replace(['%', '_'], ['\%', '\_'], $request->search);
                $all_uploads->where('file_original_name', 'like', '%' . $escaped . '%', '\\');
            }
            if ($request->type != null) {
                $all_uploads->where('type', $request->type);
            }

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


            $all_uploads = $all_uploads->paginate(30)->appends(request()->query());

            return new UploadedFileCollection($all_uploads);
        }

        return response()->json([
            "result" => false,
            "data" => []
        ]);
    }

    public function upload(Request $request, MultipartUploadService $uploads)
    {
        if (auth()->user()->user_type == 'seller') {
            if ($request->hasFile('aiz_file')) {
                $extension = strtolower($request->file('aiz_file')->getClientOriginalExtension());

                if (
                    env('DEMO_MODE') == 'On' &&
                    (MultipartUploadService::KINDS[$extension] ?? null) == 'archive'
                ) {
                    return $this->failed(translate('File has been inserted successfully'));
                }

                $uploads->store($request->file('aiz_file'), Auth::user()->id);

                return $this->success(translate('File has been inserted successfully'));
            } else {
                return $this->failed(translate("Upload file is missing"));
            }
        }

        return $this->failed(translate("You can't upload the file"));
    }

    public function destroy($id)
    {
        $upload = Upload::findOrFail($id);

        if (Gate::denies('delete', $upload)) {
            return $this->failed(translate("You don't have permission for deleting this!"));
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
           return $this->success(translate('File deleted successfully'));
        } catch (\Exception $e) {
            $upload->delete();
            return $this->failed(translate('File deleted Failed'));
        }
        return $this->success(translate('File deleted successfully'));
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

    public function all_file()
    {
        $uploads = Upload::all();
        foreach ($uploads as $upload) {
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
                flash(translate('File deleted successfully'))->success();
            } catch (\Exception $e) {
                $upload->delete();
                flash(translate('File deleted successfully'))->success();
            }
        }

        Upload::query()->truncate();

        return back();
    }

    //Download project attachment
    public function attachment_download($id)
    {
        $project_attachment = Upload::find($id);
        try {
            $file_path = public_path($project_attachment->file_name);
            return Response::download($file_path);
        } catch (\Exception $e) {
            flash(translate('File does not exist!'))->error();
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
