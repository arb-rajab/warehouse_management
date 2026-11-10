<div class="modal fade" id="wallet_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('storefront.checkout.recharge_wallet') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}"></button>
            </div>
            <div class="modal-body gry-bg px-3 pt-3" style="overflow-y: inherit;">
                <form class="" action="{{ route('wallet.recharge') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <label>{{ __('storefront.checkout.payment_method') }} <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <select class="form-control selectpicker rounded-0" data-minimum-results-for-search="Infinity"
                                    name="payment_option" data-live-search="true">
                                    @foreach (app(\App\Services\PaymentOptions::class)->available() as $gatewayKey => $gateway)
                                        <option value="{{ $gatewayKey }}">{{ $gateway['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <label>{{ __('storefront.common.amount') }} <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-8">
                            <input type="number" lang="en" class="form-control mb-3 rounded-0" name="amount"
                                placeholder="{{ __('storefront.common.amount') }}" required>
                        </div>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit"
                            class="btn btn-sm btn-primary rounded-0 transition-3d-hover mr-1">{{ __('storefront.common.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
