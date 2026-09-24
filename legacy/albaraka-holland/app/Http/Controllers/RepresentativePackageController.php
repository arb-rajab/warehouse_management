<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RepresentativePackage;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RepresentativePackageController extends Controller
{
    public function index(){

        $packages = RepresentativePackage::all();
        return view('backend.representatives.packages.index', compact('packages'));

    }

    public function create(){

        return view('backend.representatives.packages.create');
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'percentage' => 'required|numeric|max:100|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        RepresentativePackage::create([
            'name' => $request->name,
            'percentage' => $request->percentage,
        ]);

        flash('Package Created Successfully')->success();
        return redirect()->route('rep.packages.index');

    }

    public function edit($id){
        $package = RepresentativePackage::find($id);

        return view('backend.representatives.packages.edit', compact('package'));


    }

    public function update(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'percentage' => 'required|numeric|max:100|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }


        $id = $request->id;
        $package_name = $request->name;
        $package_percentage = $request->percentage;


        $package = RepresentativePackage::find($id);
        $package->name = $package_name;
        $package->percentage = $package_percentage;
        $package->save();

        $users = User::where('rep_package_id', $package->id)->get();
        foreach($users as $user){
            $user->rep_discount_percentage =  $package_percentage; // update all the reps' that have the same package we're updating right now -- bad practice i know.
            $user->save();
        }

        flash('Package updated successfully')->success();
        return redirect()->route('rep.packages.index');
    }


    public function delete($id){
        $package  = RepresentativePackage::find($id);
        if($package){
            $users = User::where('rep_package_id', $package->id)->get();
            foreach($users as $user){ // reset all the reps' that have the same package we're deleting
                $user->rep_package_id = null;
                $user->rep_discount_percentage = 0;
                $user->save();
            }
            $package->delete();
            flash('Package Deleted Successfully')->success();
        }
        else{
            flash('Error')->error();
        }

        return redirect()->route('rep.packages.index');

    }


    public function package_modal(Request $request){ // show the modal list of packages to choose from

        $user = User::findOrFail($request->id);
        $packages = RepresentativePackage::all();

        return view('backend.representatives.packages.modal', compact('user', 'packages'));

    }


    public function set_package(Request $request){ // change the rep's package (discount percentage)

        $user = User::findOrFail($request->user_id); // the rep.
        $package = RepresentativePackage::findOrFail($request->package_id);
        $user->rep_package_id =  $package->id;
        $user->rep_discount_percentage = $package->percentage;


        if($user->save()){
            flash(translate('Rep Package Updated'))->success();
        }
        else{
            flash(translate('Something Went Wrong!'))->error();
        }
        return back();
     }

}
