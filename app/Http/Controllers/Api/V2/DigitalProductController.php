<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DigitalProductController extends Controller
{
    public function download(Request $request)
    {
        $product = Product::findOrFail($request->id);
        Gate::authorize('download', $product);

        $upload = Upload::findOrFail($product->file_name);
        if (config('filesystems.default') == "s3") {
            return \Storage::disk('s3')->download($upload->file_name, $upload->file_original_name . "." . $upload->extension);
        } else {
            if (file_exists(base_path('public/' . $upload->file_name))) {
                $file = public_path()."/$upload->file_name";
                return response()->download($file,$upload->file_original_name.".".$upload->extension);
            }
        }

        abort(404);
    }
}
