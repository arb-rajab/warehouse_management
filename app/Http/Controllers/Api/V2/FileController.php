<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\UploadedFileCollection;
use App\Models\Upload;
use Illuminate\Http\Request;
use App\Services\Base64ImageUploadService;
use InvalidArgumentException;
class FileController extends Controller
{
    public function index(){
        $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('user_id',auth()->user()->id) : Upload::query();


        $all_uploads = $all_uploads->paginate(20)->appends(request()->query());

       return new UploadedFileCollection($all_uploads);

    }

    // any base 64 image through uploader
    public function imageUpload(Request $request, Base64ImageUploadService $uploads)
    {
        try {
            $upload = $uploads->store($request->image, $request->filename, auth()->id());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($upload->id),
            'upload_id' => $upload->id
        ]);
    }
}
