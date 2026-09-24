@extends('backend.layouts.app')

@section('content')

<h4 class="text-center text-muted">{{translate('System')}}</h4>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
            	<h5 class="mb-0 h6 text-center">{{translate('HTTPS Activation')}}</h5>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'FORCE_HTTPS')" <?php if(env('FORCE_HTTPS') == 'On') echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Maintenance Mode Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'maintenance_mode')" <?php if(get_setting('maintenance_mode') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Disable image encoding?')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'disable_image_optimization')" <?php if(get_setting('disable_image_optimization') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
</div>


<h4 class="text-center text-muted mt-4">{{translate('Business Related')}}</h4>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Vendor System Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'vendor_system_activation')" <?php if(get_setting('vendor_system_activation') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Classified Product')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'classified_product')" <?php if(get_setting('classified_product') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Wallet System Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'wallet_system')" <?php if(get_setting('wallet_system') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Coupon System Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'coupon_system')" <?php if(get_setting('coupon_system') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Pickup Point Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'pickup_point')" <?php if(get_setting('pickup_point') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Conversation Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'conversation_system')" <?php if(get_setting('conversation_system') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Seller Product Manage By Admin')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'product_manage_by_admin')"
                        <?php if(\App\Models\BusinessSetting::where('type', 'product_manage_by_admin')->first() &&
                                get_setting('product_manage_by_admin') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('After activate this option Cash On Delivery of Seller product will be managed by Admin')}}.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Admin Approval On Seller Product')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'product_approve_by_admin')"
                        <?php if(\App\Models\BusinessSetting::where('type', 'product_approve_by_admin')->first() &&
                                get_setting('product_approve_by_admin') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('After activate this option, Admin approval need to seller product')}}.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Email Verification')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'email_verification')" <?php if(get_setting('email_verification') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure SMTP correctly to enable this feature.') }} <a href="{{ route('smtp_settings.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Product Query Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'product_query_activation')" <?php if(get_setting('product_query_activation') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
    @if(addon_is_activated('wholesale'))
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0 h6 text-center">{{translate('Wholesale Product for Seller')}}</h3>
                </div>
                <div class="card-body text-center">
                    <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" onchange="updateSettings(this, 'seller_wholesale_product')" <?php if(get_setting('seller_wholesale_product') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    @endif
    @if(addon_is_activated('auction'))
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0 h6 text-center">{{translate('Auction Product for Seller')}}</h3>
                </div>
                <div class="card-body text-center">
                    <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" onchange="updateSettings(this, 'seller_auction_product')" <?php if(get_setting('seller_auction_product') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    @endif
</div>


<h4 class="text-center text-muted mt-4">{{translate('Admin Dashboard Sidenav')}}</h4>
<div class="row">

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Products')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1 ">
                    In_House_Products_Section
                    <input type="checkbox" onchange="updateSettings(this, 'In_House_Products_Section')" <?php if(get_setting('In_House_Products_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Digital_Products_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Digital_Products_Section')" <?php if(get_setting('Digital_Products_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Product Reviews and Rating
                    <input type="checkbox" onchange="updateSettings(this, 'Product_Reviews_Section')" <?php if(get_setting('Product_Reviews_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Sales')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    In_House_Orders_Section
                    <input type="checkbox" onchange="updateSettings(this, 'In_House_Orders_Section')" <?php if(get_setting('In_House_Orders_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Pickup_Orders_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Pickup_Orders_Section')" <?php if(get_setting('Pickup_Orders_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Sellers')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Payouts_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Payouts_Section')" <?php if(get_setting('Payouts_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Payout_request_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Payout_request_Section')" <?php if(get_setting('Payout_request_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Seller_Commission_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Seller_Commission_Section')" <?php if(get_setting('Seller_Commission_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Seller_Verify_Form_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Seller_Verify_Form_Section')" <?php if(get_setting('Seller_Verify_Form_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

              <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                        Shop Ratings
                   <input type="checkbox" onchange="updateSettings(this, 'Shop_Ratings')" <?php if(get_setting('Shop_Ratings') == 1) echo "checked";?>>
                       <span class="slider round"></span>
                </label>


            </div>
        </div>
    </div>


</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Reports')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Reports_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Reports_Section')" <?php if(get_setting('Reports_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    In_House_Sales_Report
                    <input type="checkbox" onchange="updateSettings(this, 'In_House_Sales_Report')" <?php if(get_setting('In_House_Sales_Report') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Products_Stock_Report
                    <input type="checkbox" onchange="updateSettings(this, 'Products_Stock_Report')" <?php if(get_setting('Products_Stock_Report') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Products_Wishlist_Report
                    <input type="checkbox" onchange="updateSettings(this, 'Products_Wishlist_Report')" <?php if(get_setting('Products_Wishlist_Report') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    User_Searches_Report
                    <input type="checkbox" onchange="updateSettings(this, 'User_Searches_Report')" <?php if(get_setting('User_Searches_Report') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Commission_History_Report
                    <input type="checkbox" onchange="updateSettings(this, 'Commission_History_Report')" <?php if(get_setting('Commission_History_Report') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Wallet_Recharge_History
                    <input type="checkbox" onchange="updateSettings(this, 'Wallet_Recharge_History')" <?php if(get_setting('Wallet_Recharge_History') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>



            </div>
        </div>
    </div>



    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Blogs')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Blog_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Blog_Section')" <?php if(get_setting('Blog_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

            </div>
        </div>
    </div>



    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Marketing')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Marketing_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Marketing_Section')" <?php if(get_setting('Marketing_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Flash_deals
                    <input type="checkbox" onchange="updateSettings(this, 'Flash_deals')" <?php if(get_setting('Flash_deals') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Newsletters
                    <input type="checkbox" onchange="updateSettings(this, 'Newsletters')" <?php if(get_setting('Newsletters') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Support System')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Support_Section
                    <input type="checkbox" onchange="updateSettings(this, 'Support_Section')" <?php if(get_setting('Support_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>


    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Website Setup')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Website Setup
                    <input type="checkbox" onchange="updateSettings(this, 'website_setup')" <?php if(get_setting('website_setup') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Website Setup - Header
                    <input type="checkbox" onchange="updateSettings(this, 'website_setup_header')" <?php if(get_setting('website_setup_header') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Website Setup - Footer
                    <input type="checkbox" onchange="updateSettings(this, 'website_setup_footer')" <?php if(get_setting('website_setup_footer') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Website Setup - Pages
                    <input type="checkbox" onchange="updateSettings(this, 'website_setup_pages')" <?php if(get_setting('website_setup_pages') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Website Setup - Appearance
                    <input type="checkbox" onchange="updateSettings(this, 'website_setup_appearance')" <?php if(get_setting('website_setup_appearance') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Website setup & Configuration')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Setup & Configuration
                    <input type="checkbox" onchange="updateSettings(this, 'Setup_configuration_section')" <?php if(get_setting('Setup_configuration_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Languages_section
                    <input type="checkbox" onchange="updateSettings(this, 'Languages_section')" <?php if(get_setting('Languages_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    currency_section
                    <input type="checkbox" onchange="updateSettings(this, 'currency_section')" <?php if(get_setting('currency_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    VAT_TAX_section
                    <input type="checkbox" onchange="updateSettings(this, 'VAT_TAX_section')" <?php if(get_setting('VAT_TAX_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Setup_pickup_point_section
                    <input type="checkbox" onchange="updateSettings(this, 'Setup_pickup_point_section')" <?php if(get_setting('Setup_pickup_point_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    SMTP_Section
                    <input type="checkbox" onchange="updateSettings(this, 'SMTP_Section')" <?php if(get_setting('SMTP_Section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Order_configuration_section
                    <input type="checkbox" onchange="updateSettings(this, 'Order_configuration_section')" <?php if(get_setting('Order_configuration_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Socail_Media_logins_section
                    <input type="checkbox" onchange="updateSettings(this, 'Socail_Media_logins_section')" <?php if(get_setting('Socail_Media_logins_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Facebook_section
                    <input type="checkbox" onchange="updateSettings(this, 'Facebook_section')" <?php if(get_setting('Facebook_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Setup_Shipping_section
                    <input type="checkbox" onchange="updateSettings(this, 'Setup_Shipping_section')" <?php if(get_setting('Setup_Shipping_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>


                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    QR Connections System
                    <input type="checkbox" onchange="updateSettings(this, 'qr_connection_system')" <?php if(get_setting('qr_connection_system') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>

            </div>
        </div>
    </div>


    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Staff')}}</h3>
            </div>
            <div class="card-body text-center">
                <label  class="aiz-switch aiz-switch-success mb-0 d-flex align-items-center justify-content-between py-1">
                    Staff
                    <input type="checkbox" onchange="updateSettings(this, 'Staffs_section')" <?php if(get_setting('Staffs_section') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
    </div>
</div>


<h4 class="text-center text-muted mt-4">{{translate('Payment Related')}}</h4>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header text-center bord-btm">
                <h3 class="mb-0 h6 text-center">{{translate('Paypal Payment Activation')}}</h3>
            </div>
            <div class="card-body">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/paypal.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'paypal_payment')" <?php if(get_setting('paypal_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert text-center" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Paypal correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Stripe Payment Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img   class="float-left" src="{{ static_asset('assets/img/cards/stripe.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'stripe_payment')" <?php if(get_setting('stripe_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Stripe correctly to enable this feature.') }} <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Mercadopago Payment Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img   class="float-left" src="{{ static_asset('assets/img/cards/mercadopago.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'mercadopago_payment')" <?php if(get_setting('mercadopago_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Mercadopago correctly to enable this feature.') }} <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('SSlCommerz Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/sslcommerz.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'sslcommerz_payment')" <?php if(get_setting('sslcommerz_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure SSlCommerz correctly to enable this feature.') }} <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>


    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Instamojo Payment Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/instamojo.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'instamojo_payment')" <?php if(get_setting('instamojo_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Instamojo Payment correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Razor Pay Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/rozarpay.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'razorpay')" <?php if(get_setting('razorpay') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Razor correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('PayStack Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/paystack.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'paystack')" <?php if(get_setting('paystack') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure PayStack correctly to enable this feature')  }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>


    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('VoguePay Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/vogue.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'voguepay')" <?php if(get_setting('voguepay') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure VoguePay correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Payhere Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/payhere.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'payhere')" <?php if(get_setting('payhere') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Payhere correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Ngenius Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/ngenius.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'ngenius')" <?php if(get_setting('ngenius') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Ngenius correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Iyzico Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/iyzico.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'iyzico')" <?php if(get_setting('iyzico') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure iyzico correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Bkash Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/bkash.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'bkash')" <?php if(get_setting('bkash') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure bkash correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Nagad Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/nagad.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'nagad')" <?php if(get_setting('nagad') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure nagad correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Proxy Pay Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/proxypay.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'proxypay')" <?php if(get_setting('proxypay') == '1') echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure proxypay correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Amarpay Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/aamarpay.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'aamarpay')" @if(get_setting('aamarpay') == '1') checked @endif>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure amarpay correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Authorize Net Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/authorizenet.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'authorizenet')" <?php if(get_setting('authorizenet') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure authorize net correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Payku Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/payku.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'payku')" <?php if(get_setting('payku') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure payku net correctly to enable this feature') }}. <a href="{{ route('payment_method.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Cash Payment Activation')}}</h3>
            </div>
            <div class="card-body text-center">
                <div class="clearfix">
                    <img class="float-left" src="{{ static_asset('assets/img/cards/cod.png') }}" height="30">
                    <label class="aiz-switch aiz-switch-success mb-0 float-right">
                        <input type="checkbox" onchange="updateSettings(this, 'cash_payment')" <?php if(get_setting('cash_payment') == 1) echo "checked";?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

</div>

<h4 class="text-center text-muted mt-4">{{translate('Social Media Login')}}</h4>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Facebook login')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'facebook_login')" <?php if(get_setting('facebook_login') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Facebook Client correctly to enable this feature') }}. <a href="{{ route('social_login.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Google login')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'google_login')" <?php if(get_setting('google_login') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Google Client correctly to enable this feature') }}. <a href="{{ route('social_login.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Twitter login')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'twitter_login')" <?php if(get_setting('twitter_login') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Twitter Client correctly to enable this feature') }}. <a href="{{ route('social_login.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0 h6 text-center">{{translate('Apple login')}}</h3>
            </div>
            <div class="card-body text-center">
                <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" onchange="updateSettings(this, 'apple_login')" <?php if(get_setting('apple_login') == 1) echo "checked";?>>
                    <span class="slider round"></span>
                </label>
                <div class="alert" style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
                    {{ translate('You need to configure Apple Client correctly to enable this feature') }}. <a href="{{ route('social_login.index') }}">{{ translate('Configure Now') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
    <script type="text/javascript">
        function updateSettings(el, type){
            if($(el).is(':checked')){
                var value = 1;
            }
            else{
                var value = 0;
            }

            $.post('{{ route('business_settings.update.activation') }}', {_token:'{{ csrf_token() }}', type:type, value:value}, function(data){
                if(data == '1'){
                    AIZ.plugins.notify('success', '{{ translate('Settings updated successfully') }}');
                }
                else{
                    AIZ.plugins.notify('danger', 'Something went wrong');
                }
            });
        }
    </script>
@endsection
