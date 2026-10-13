@extends('shop.backend.layouts.app')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <div class="card">
        <form class="" id="sort_visits" action="" method="GET">
            <div class="card-header">
                <h1 class="h2 fs-16 mb-0">{{ translate('Visit Details') }}</h1>
                <h1 class="h2 fs-16 mb-0">{{ $rep->name . "'s " . translate('Visits')}}</h1>

                <div class="col-lg-2">
                    <div class="form-group mb-0">
                        <input type="date" class="form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" autocomplete="off">
                        {{-- <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off"> --}}
                    </div>
                </div>

                <div class="col-auto">
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row gutters-5">
                    <div class="col text-md-left text-center">
                    </div>
                </div>
                {{-- <hr class="new-section-sm bord-no"> --}}
                @if($visits->count() > 0)
                    <div class="row">
                        <div class="col-lg-12">
                            <div id="map" style="height: 30rem; width: auto; border:0; margin: auto;"></div>
                        </div>
                    </div>
                @else
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            <div class="alert alert-warning" role="alert">
                                <h2><i class="fas fa-exclamation-circle"></i> {{ translate('No visits found on this date') }}</h2>
                                <p>{{ translate('Please try selecting a different date.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </div>

    @include('shop.backend.representatives.visits.map')
@endsection
