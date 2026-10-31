@extends('shop.frontend.layouts.app')

@section('content')
    <!-- Customers Details -->
    <section onload="getLocation()" class="mb-4" id="cart-summary">
        @include('shop.frontend.partials.user_details', ['users' => $users])
    </section>
@endsection


@section('modal')
    <!-- note notes Modal -->
    <div id="note-modal" class="modal fade">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title h6">{{__('storefront.common.visit_note')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                </div>
                <form id="visit-note-form" class="modal-body text-center" action="{{ route('visits.note') }}" method="POST">
                    @csrf
                    <input type="hidden" id="visit-note-id" name="visit_id">
                    <textarea id="note" name="note" class="form-control" rows="4"></textarea>
                    <button type="button" class="btn btn-secondary rounded-0 mt-2" data-dismiss="modal">{{__('storefront.common.cancel')}}</button>
                    <button type="submit" class="btn btn-primary rounded-0 mt-2">{{__('storefront.common.update')}}</button>
                </form>
            </div>
        </div>
    </div><!-- /.modal -->
@endsection

@section('script')
    <script type="text/javascript">
        function removeFromCartView(e, key) {
            e.preventDefault();
            removeFromCart(key);
        }


        function getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by this browser.'));
            }

            navigator.geolocation.getCurrentPosition(resolve, reject);
        });
        }

        async function chooseCustomer(id, element) {
        let position;
        try {
            position = await getCurrentPosition();
        } catch (err) {
            alert({{ \Illuminate\Support\Js::from(__('storefront.common.location_access_failed')) }}.replace(':error', err.message));
            return;
        }

        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;

        var li = $(element.closest('.list-group-item'))

        $.ajax({
            type: "POST",
            url: '{{ route('checkout.chooseCustomer') }}',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                id: id,
                latitude: latitude,
                longitude: longitude
            },
            success: onCustomerChooseSuccess,
            error: onCustomerChooseError
        });
        }

        // Handle the success case
        function onCustomerChooseSuccess(data) {
            if (data.open_visit['has_open_visit'] == true) {
                        document.getElementById('visit-note-id').value = data.open_visit['visit_id'];
                        $('#note-modal').modal('show');
                    }
                    $(element).addClass('disabled');
                    $(li).addClass('bg-success rounded-2');
                    $('.btn-circle').not(element).removeClass('disabled');
                    $(li).closest('ul').find('li').not(li).removeClass('bg-success rounded-2');

                    AIZ.extra.plusMinus();
                    AIZ.plugins.slickCarousel();
                    if (data.message) {
                        // Show a notification with danger type
                        AIZ.plugins.notify('danger', data.message);
                    }
                    AIZ.plugins.notify('success', {{ \Illuminate\Support\Js::from(__('storefront.common.user_chosen')) }});
                    window.location.href = '{{ route('home') }}';
        }

        // Handle the error case
        function onCustomerChooseError(error) {
            AIZ.plugins.notify('warning', data.responseJSON.error);
        }


        function showLoginModal() {
            $('#login_modal').modal();
        }
    </script>
@endsection
