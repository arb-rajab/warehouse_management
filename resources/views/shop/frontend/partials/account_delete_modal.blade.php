<script>
    function account_delete_confirm_modal(delete_url)
    {
        jQuery('#account_delete_confirm').modal('show', {backdrop: 'static'});
        document.getElementById('account_delete_link').setAttribute('href' , delete_url);
    }
</script>

<div class="modal fade" id="account_delete_confirm" tabindex="-1" role="dialog" aria-labelledby="account_delete_confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header d-block py-4">
                <div class="d-flex justify-content-center">
                    <span class="avatar avatar-md mb-2 mt-2">
                        @if (Auth::check() && Auth::user()->avatar_original != null)
                            <img src="{{ uploaded_asset(Auth::user()->avatar_original) }}" class="m-auto"
                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                        @else
                            <img src="{{ static_asset('assets/img/avatar-place.png') }}" class="image rounded-circle m-auto"
                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                        @endif
                    </span>
                </div>
                <h4 class="modal-title text-center fw-700" id="account_delete_confirmModalLabel" style="color: #ff9819;">{{ __('storefront.auth.delete_your_account')}}</h4>
                <p class="fs-16 fw-600 text-center" style="color: #8d8d8d;">{{ __('storefront.common.warning_you_cannot_undo_this_action')}}</p>
            </div>

            <div class="modal-body pt-3 pb-5 px-xl-5">
                <p class="text-danger mt-3"><i><strong>{{ __('storefront.common.note')}}&nbsp;{{ __('storefront.auth.don_t_click_to_any_button_or_don_t_do_any_action_during_account_deletion_it_may_takes_some_times')}}</strong></i></p>
                <p class="fs-14 fw-700" style="color: #8d8d8d;">{{ __('storefront.auth.deleting_account_means')}}</p>
                <div class="row bg-soft-warning py-2 mb-2 ml-0 mr-0 border-left border-width-2 border-danger">
                    <div class="col-1">
                        <img src="{{ static_asset('assets/img/warning.png') }}" class="h-20px">
                    </div>
                    <div class="col">
                        <p class="fw-600 mb-0">{{ __('storefront.auth.if_you_create_any_classified_ptoducts_after_deleting_your_account_those_products_will_no_longer_in_our_system') }}</p>
                    </div>
                </div>
                <div class="row bg-soft-warning py-3 ml-0 mr-0 border-left border-width-2 border-danger">
                    <div class="col-1">
                        <img src="{{ static_asset('assets/img/warning.png') }}" class="h-20px">
                    </div>
                    <div class="col">
                        <p class="fw-600 mb-0">{{ __('storefront.auth.after_deleting_your_account_wallet_balance_will_no_longer_in_our_system') }}</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-0 w-150px" data-dismiss="modal">{{ __('storefront.common.cancel')}}</button>
                <a id="account_delete_link" data-method="post" class="btn btn-danger rounded-0 w-150px">{{ __('storefront.auth.delete_account')}}</a>
            </div>
        </div>
    </div>
</div>
