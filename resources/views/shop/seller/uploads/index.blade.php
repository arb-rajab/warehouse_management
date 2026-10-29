@extends('shop.seller.layouts.app')

@section('panel_content')
<div class="aiz-titlebar text-left mt-2 mb-3">
	<div class="row align-items-center">
		<div class="col-md-6">
			<h1 class="h3">{{__('seller.uploads.all_uploaded_files')}}</h1>
		</div>
		<div class="col-md-6 text-md-right">
			<a href="{{ route('seller.uploads.create') }}" class="btn btn-primary">
				<span>{{__('seller.uploads.upload_new_file')}}</span>
			</a>
		</div>
	</div>
</div>

<div class="card">
    <form id="sort_uploads" action="">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-0 h6">{{__('seller.uploads.all_files')}}</h5>
            </div>
			<div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{__('seller.uploads.bulk_action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{__('seller.uploads.delete_selection')}}</a>
                </div>
            </div>
            <div class="col-md-3 ml-auto mr-0">
                <select class="form-control form-control-xs aiz-selectpicker" name="sort" onchange="sort_uploads()">
                    <option value="newest" @if($sort_by == 'newest') selected="" @endif>{{ __('storefront.common.sort_by_newest') }}</option>
                    <option value="oldest" @if($sort_by == 'oldest') selected="" @endif>{{ __('storefront.common.sort_by_oldest') }}</option>
                    <option value="smallest" @if($sort_by == 'smallest') selected="" @endif>{{ __('storefront.common.sort_by_smallest') }}</option>
                    <option value="largest" @if($sort_by == 'largest') selected="" @endif>{{ __('storefront.common.sort_by_largest') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-xs" name="search" placeholder="{{ __('storefront.upload.search_your_files') }}" value="{{ $search }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('storefront.common.search') }}</button>
            </div>
        </div>
    
		<div class="card-body">
			<div class="form-group">
				<div class="aiz-checkbox-inline">
					<label class="aiz-checkbox">
						{{ __('seller.uploads.select_all')}}
						<input type="checkbox" class="check-all">
						<span class="aiz-square-check"></span>
					</label>
				</div>
			</div>

			<div class="row gutters-5">
				@foreach($all_uploads as $key => $file)
					@php
						if($file->file_original_name == null){
							$file_name = __('storefront.status.unknown');
						}else{
							$file_name = $file->file_original_name;
						}
					@endphp
					<div class="col-auto w-140px w-lg-220px">
						<div class="aiz-file-box">
							<div class="dropdown-file" >
								<a class="dropdown-link" data-toggle="dropdown">
									<i class="la la-ellipsis-v"></i>
								</a>
								<div class="dropdown-menu dropdown-menu-right">
									<a href="javascript:void(0)" class="dropdown-item" onclick="detailsInfo(this)" data-id="{{ $file->id }}">
										<i class="las la-info-circle mr-2"></i>
										<span>{{ __('seller.uploads.details_info') }}</span>
									</a>
									<a href="{{ my_asset($file->file_name) }}" target="_blank" download="{{ $file_name }}.{{ $file->extension }}" class="dropdown-item">
										<i class="la la-download mr-2"></i>
										<span>{{ __('storefront.common.download') }}</span>
									</a>
									<a href="javascript:void(0)" class="dropdown-item" onclick="copyUrl(this)" data-url="{{ my_asset($file->file_name) }}">
										<i class="las la-clipboard mr-2"></i>
										<span>{{ __('seller.uploads.copy_link') }}</span>
									</a>
									<a href="javascript:void(0)" class="dropdown-item confirm-alert" data-method="post" data-href="{{ route('seller.my_uploads.destroy', $file->id ) }}" data-target="#delete-modal">
										<i class="las la-trash mr-2"></i>
										<span>{{ __('storefront.common.delete') }}</span>
									</a>
								</div>
							</div>

							<div class="select-box">
								<div class="aiz-checkbox-inline">
									<label class="aiz-checkbox">
										<input type="checkbox" class="check-one" name="id[]" value="{{$file->id}}">
										<span class="aiz-square-check"></span>
									</label>
								</div>
							</div>

							<div class="card card-file aiz-uploader-select c-default" title="{{ $file_name }}.{{ $file->extension }}">
								<div class="card-file-thumb">
									@if($file->type == 'image')
										<img src="{{ my_asset($file->file_name) }}" class="img-fit">
									@elseif($file->type == 'video')
										<i class="las la-file-video"></i>
									@else
										<i class="las la-file"></i>
									@endif
								</div>
								<div class="card-body">
									<h6 class="d-flex">
										<span class="text-truncate title">{{ $file_name }}</span>
										<span class="ext">.{{ $file->extension }}</span>
									</h6>
									<p>{{ formatBytes($file->file_size) }}</p>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
			<div class="aiz-pagination mt-3">
				{{ $all_uploads->appends(request()->input())->links() }}
			</div>
		</div>
	</form>
</div>
@endsection
@section('modal')
<div id="delete-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{ __('storefront.common.delete_confirmation') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mt-1">{{ __('seller.uploads.are_you_sure_to_delete_this_file') }}</p>
                <button type="button" class="btn btn-link mt-2" data-dismiss="modal">{{ __('storefront.common.cancel') }}</button>
                <a href="" class="btn btn-primary mt-2 comfirm-link">{{ __('storefront.common.delete') }}</a>
            </div>
        </div>
    </div>
</div>
<div id="info-modal" class="modal fade">
	<div class="modal-dialog modal-dialog-right">
			<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title h6">{{ __('seller.uploads.file_info') }}</h5>
				<button type="button" class="close" data-dismiss="modal">
				</button>
			</div>
			<div class="modal-body c-scrollbar-light position-relative" id="info-modal-content">
				<div class="c-preloader text-center absolute-center">
                    <i class="las la-spinner la-spin la-3x opacity-70"></i>
                </div>
			</div>
		</div>
	</div>
</div>

@endsection
@section('script')
	<script type="text/javascript">
		function detailsInfo(e){
            $('#info-modal-content').html('<div class="c-preloader text-center absolute-center"><i class="las la-spinner la-spin la-3x opacity-70"></i></div>');
			var id = $(e).data('id')
			$('#info-modal').modal('show');
			$.post('{{ route('seller.my_uploads.info') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                $('#info-modal-content').html(data);
				// console.log(data);
			});
		}
		function copyUrl(e) {
			var url = $(e).data('url');
			var $temp = $("<input>");
		    $("body").append($temp);
		    $temp.val(url).select();
		    try {
			    document.execCommand("copy");
			    AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.common.link_copied_to_clipboard')) }});
			} catch (err) {
			    AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.oops_unable_to_copy')) }});
			}
		    $temp.remove();
		}
        function sort_uploads(el){
            $('#sort_uploads').submit();
        }

		$(document).on("change", ".check-all", function() {
			if(this.checked) {
				// Iterate each checkbox
				$('.check-one:checkbox').each(function() {
					this.checked = true;
				});
			} else {
				$('.check-one:checkbox').each(function() {
					this.checked = false;
				});
			}
		});

		function bulk_delete() {
            var data = new FormData($('#sort_uploads')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('seller.bulk-uploaded-files-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response == 1) {
						location.reload();
                    }
					else{
						AIZ.plugins.notify('danger', {{ \Illuminate\Support\Js::from(__('storefront.common.something_went_wrong_label')) }});
					}
                }
            });
        }
	</script>
@endsection