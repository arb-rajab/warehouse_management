@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar mt-2 mb-3">
	<div class="row align-items-center">
		<div class="col-md-6">
			<h1 class="h3">{{translate('All Packages')}}</h1>
		</div>
		<div class="col-md-6 text-md-right">
			<a href="{{ route('rep.packages.create') }}" class="btn btn-circle btn-info">
				<span>{{translate('Add New Package')}}</span>
			</a>
		</div>
	</div>
</div>


<div class="row">
    @foreach ($packages as $key => $package)
        <div class="col-lg-4 col-md-4 col-sm-12">
            <div class="card">
                <div class="card-body text-center">

					<p class="mb-3 h5 fw-600">Name: {{ $package->name }}</p>
                    <p class="h6 text-success">Percentage: %{{$package->percentage}}</p>



                    {{-- <p class="fs-15">{{translate('Number of Sellers Subscribed') }}:
                        <b class="text-bold">{{$shops->where('seller_package_id', $package->id)->count()}} {{translate('Sellers')}}</b>
                    </p> --}}

                    <div class="mar-top">
                        <a href="{{route('rep.packages.edit', $package->id )}}" class="btn btn-sm btn-info">{{translate('Edit')}}</a>
                        <a href="#" data-href="{{route('rep.packages.destroy', $package->id)}}" class="btn btn-sm btn-danger confirm-delete">{{translate('Delete')}}</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection
