@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
	<div class="row align-items-center">
		<div class="col-md-6">
			<h1 class="h3">{{translate('All Offer Deals')}}</h1>
		</div>
        @can('add_offer_deal')
            <div class="col-md-6 text-md-right">
                <a href="{{ route('offer_deals.create') }}" class="btn btn-circle btn-info">
                    <span>{{translate('Create New Offer')}}</span>
                </a>
                <a href="{{ route('offer_deals.brands.create') }}" class="btn btn-circle btn-info">
                    <span>{{translate('Create New Brand Offer')}}</span>
                </a>
            </div>
        @endcan
	</div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0 h6">{{translate('Offer Deals')}}</h5>
        <div class="pull-right clearfix">
            <form class="" id="sort_offer_deals" action="" method="GET">
                <div class="box-inline pad-rgt pull-left">
                    <div class="" style="min-width: 200px;">
                        <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type name & Enter') }}">
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card-body">
        <table class="table aiz-table mb-0" >
            <thead>
                <tr>
                    <th data-breakpoints="lg">#</th>
                    <th data-breakpoints="lg">{{ translate('Banner') }}</th>
                    <th>{{translate('Title')}}</th>
                    <th data-breakpoints="lg">{{ translate('Start Date') }}</th>
                    <th data-breakpoints="lg">{{ translate('End Date') }}</th>
                    <th data-breakpoints="lg">{{ translate('Status') }}</th>
                    <th data-breakpoints="lg">{{ translate('Multiple usage') }}</th>
                    <th data-breakpoints="lg">{{ translate('Repeatable') }}</th>
                    <th data-breakpoints="lg">{{ translate('Offer Type') }}</th>
                    <th class="text-right">{{translate('Options')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offer as $key => $offer_deal)
                    <tr>
                        <td>{{ ($key+1) + ($offer->currentPage() - 1)*$offer->perPage() }}</td>
                        <td><img src="{{ uploaded_asset($offer_deal->banner) }}" alt="banner" class="h-50px"></td>
                        <td>{{ $offer_deal->title }}</td>
                        <td>{{ date('d-m-Y H:i:s', $offer_deal->start_date) }}</td>
                        <td>{{ date('d-m-Y H:i:s', $offer_deal->end_date) }}</td>
                        <td>
							<label class="aiz-switch aiz-switch-success mb-0">
								<input onchange="update_offer_deal_status(this)" value="{{ $offer_deal->id }}" type="checkbox" @if ($offer_deal->is_active == 1) checked @endif>
								<span class="slider round"></span>
							</label>
						</td>
                        <td>
                            @if ($offer_deal->offer_type == 'products_offer')
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input onchange="update_offer_deal_multiple_usage(this)" value="{{ $offer_deal->id }}" type="checkbox" @if ($offer_deal->multiple_usage == 1) checked @endif>
                                    <span class="slider round"></span>
                                </label>
                            @endif
						</td>
                        <td>
                            @if ($offer_deal->offer_type == 'products_offer')
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input onchange="update_offer_deal_repeatable_status(this)" value="{{ $offer_deal->id }}" type="checkbox" @if ($offer_deal->is_repeatable == 1) checked @endif>
                                    <span class="slider round"></span>
                                </label>
                            @endif

						</td>
                        <td>
                            {{ $offer_deal->offer_type }}
                        </td>
						<td class="text-right">
                            @can('edit_offer_deal')
                            @php
                                if($offer_deal->offer_type == 'brand_offer'){
                                    $url = route('offer_deals.edit', ['id'=>$offer_deal->id, 'type' => 'brand', 'lang'=>env('DEFAULT_LANGUAGE')] );
                                }else{
                                    $url = route('offer_deals.edit', ['id'=>$offer_deal->id, 'type' => 'product', 'lang'=>env('DEFAULT_LANGUAGE')] );
                                }
                            @endphp
                                <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ $url }}" title="{{ translate('Edit') }}">
                                    <i class="las la-edit"></i>
                                </a>
                            @endcan
                            @can('delete_offer_deal')
                                <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('offer_deals.destroy', $offer_deal->id)}}" title="{{ translate('Delete') }}">
                                    <i class="las la-trash"></i>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="clearfix">
            <div class="pull-right">
                {{ $offer->appends(request()->input())->links() }}
            </div>
        </div>
    </div>
</div>

@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        function update_offer_deal_status(el){
            if(el.checked){
                var status = 1;
            }
            else{
                var status = 0;
            }
            $.post('{{ route('offer_deals.update_status') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    location.reload();
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
        function update_offer_deal_repeatable_status(el){
            if(el.checked){
                var repeatable = 1;
            }
            else{
                var repeatable = 0;
            }
            $.post('{{ route('offer_deals.update_repeatable') }}', {_token:'{{ csrf_token() }}', id:el.value, repeatable:repeatable}, function(data){
                if(data == 1){
                    location.reload();
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
        function update_offer_deal_multiple_usage(el){
            if(el.checked){
                var multiple_usage = 1;
            }
            else{
                var multiple_usage = 0;
            }
            $.post('{{ route('offer_deals.update_multiple_usage') }}', {_token:'{{ csrf_token() }}', id:el.value, multiple_usage:multiple_usage}, function(data){
                if(data == 1){
                    location.reload();
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
    </script>
@endsection
