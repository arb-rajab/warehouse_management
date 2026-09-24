@extends('backend.layouts.app')

@section('content')



<div class="mx-4 my-4">
    <div class="col justify-content-center">
        <a href="javascript:void(0)" onclick="add_offer_modal()" class="btn btn-primary btn-block ">{{ translate('Add new file') }}</a>
    </div>
</div>


<div class="card">
    <div class="card-header row gutters-5">
        <div class="col">
            <h5 class="mb-0 h6">{{translate('Offers Catalogs')}}</h5>
        </div>

    </div>
    <div class="card-body">
        <table class="table aiz-table mb-0">
            <thead>
                <tr>
                    <th>{{translate('File')}}</th>
                    <th>{{ translate('Created At') }}</th>
                    <th>{{ translate('Options') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offers as $key => $offer)
                    @if ($offer != null)
                <tr>
                <td>
                    <a href="{{ route('offers.download')}}"> Download pdf</a>
                </td>


                <td>
                    {{ $offer->created_at }}
                </td>

                <td>
                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('offers.destroy', $offer->id)}}" title="{{ translate('Delete') }}">
                                            <i class="las la-trash"></i>
                                        </a>
                </td>

                </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>


<!-- modal -->
<div class="modal fade" id="create_modal" tabindex="-1" role="dialog"  aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-body pt-3 pb-5 px-xl-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 h6">{{translate('Add Catalog')}}</h5>
                        <span>Only one catalog can be uploaded</span>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('offers.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group row">
                                <label class="col-md-3 col-form-label">{{ translate('Upload PDF File') }}
                                <input type="file" name="catalog" accept=".pdf" required>
                                </label>
                            </div>
                            <div class="form-group mb-0 text-right">
                                <button type="submit" class="btn btn-primary">{{translate('Submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- end modal -->

@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

<script>
    function add_offer_modal()
    {
        jQuery('#create_modal').modal('show', {backdrop: 'static'});

    }
</script>



