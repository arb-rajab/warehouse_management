
<div class="modal-body">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{translate('Choose Package')}}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('rep.set-package') }}" method="POST">
            	@csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">

                <div class="form-group">
                    <label class="col-sm-3 col-from-label" for="list">{{translate('Package Name')}} <span style="color: red;">*</span></label>
                    <div class="col-sm-9">
                        <select id="list" class="form-control aiz-selectpicker" name="package_id" required>
                          @foreach($packages as $package)
                          <option value="{{ $package->id }}" @if($package->id == $user->rep_package_id) selected @endif >{{ $package->name . " [ %" . $package->percentage ."]"}}  </option>
                          @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-primary">{{translate('Submit')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>

