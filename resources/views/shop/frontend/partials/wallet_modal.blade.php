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
                                    @if (get_setting('toyyibpay_payment') == 1)
                                        <option value="toyyibpay">{{ __('storefront.common.toyyibpay') }}</option>
                                    @endif
                                    @if (get_setting('sslcommerz_payment') == 1)
                                        <option value="sslcommerz">{{ __('storefront.common.sslcommerz_label') }}</option>
                                    @endif
                                    @if (get_setting('voguepay') == 1)
                                        <option value="voguepay">{{ __('storefront.common.voguepay') }}</option>
                                    @endif
                                    @if (get_setting('payhere') == 1)
                                        <option value="payhere">{{ __('storefront.common.payhere_label') }}</option>
                                    @endif
                                    @if (get_setting('ngenius') == 1)
                                        <option value="ngenius">{{ __('storefront.common.ngenius_label') }}</option>
                                    @endif
                                    @if (get_setting('iyzico') == 1)
                                        <option value="iyzico">{{ __('storefront.common.iyzico') }}</option>
                                    @endif
                                    @if (get_setting('bkash') == 1)
                                        <option value="bkash">{{ __('storefront.common.bkash') }}</option>
                                    @endif
                                    @if (get_setting('nagad') == 1)
                                        <option value="nagad">{{ __('storefront.common.nagad') }}</option>
                                    @endif
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
