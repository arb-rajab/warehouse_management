@extends('shop.seller.layouts.app')

@section('panel_content')
<div class="aiz-titlebar text-left mt-2 mb-3">
	<div class="row align-items-center">
		<div class="col-md-6">
			<h1 class="h3">{{__('seller.uploads.upload_new_file')}}</h1>
		</div>
		<div class="col-md-6 text-md-right">
			<a href="{{ route('seller.uploaded-files.index') }}" class="btn btn-link text-reset">
				<i class="las la-angle-left"></i>
				<span>{{__('seller.uploads.back_to_uploaded_files')}}</span>
			</a>
		</div>
	</div>
</div>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0 h6">{{__('seller.uploads.drag_drop_your_files')}}</h5>
    </div>
    <div class="card-body">
    	<div id="aiz-upload-files" class="h-420px" style="min-height: 65vh">
    		
    	</div>
    </div>
</div>
@endsection

@section('script')
	<script type="text/javascript">
		$(document).ready(function() {
			AIZ.plugins.aizUppy();
		});
	</script>
@endsection