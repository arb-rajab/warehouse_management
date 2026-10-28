@extends('shop.seller.layouts.app')

@section('panel_content')

<div class="card">
    <form class="" id="sort_customers" action="" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-0 h6">{{__('storefront.navigation.notifications')}}</h5>
            </div>
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                @forelse($notifications as $notification)
                    @if($notification->type == 'App\Notifications\OrderNotification')
                        <li class="list-group-item d-flex justify-content-between align-items- py-3">
                            <div class="media text-inherit">
                                <div class="media-body">
                                    <p class="mb-1 text-truncate-2">
                                        <a href="{{ route('seller.orders.show', encrypt($notification->data['order_id'])) }}">
                                            {{ __('seller.orders.notification', ['code' => $notification->data['order_code'], 'status' => \App\Support\StoreLocale::status($notification->data['status'])]) }}
                                        </a>
                                    </p>
                                    <small class="text-muted">
                                        {{ date("F j Y, g:i a", strtotime($notification->created_at)) }}
                                    </small>
                                </div>
                            </div>
                        </li>
                    @endif

                @empty
                    <li class="list-group-item">
                        <div class="py-4 text-center fs-16">{{ __('storefront.navigation.no_notification_found') }}</div>
                    </li>
                @endforelse
            </ul>

            {{ $notifications->links() }}
        </div>
    </form>
</div>

@endsection

@section('modal')
    <div class="modal fade" id="order_details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div id="order-details-modal-body">

                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">
        
        function show_order_details(order_id)
        {
            $('#order-details-modal-body').html(null);

            if(!$('#modal-size').hasClass('modal-lg')){
                $('#modal-size').addClass('modal-lg');
            }

            $.post('{{ route('orders.details') }}', { _token : AIZ.data.csrf, order_id : order_id}, function(data){
                $('#order-details-modal-body').html(data);
                $('#order_details').modal();
                $('.c-preloader').hide();
            });
        }
        function sort_orders(el){
            $('#sort_orders').submit();
        }
    </script>
@endsection

