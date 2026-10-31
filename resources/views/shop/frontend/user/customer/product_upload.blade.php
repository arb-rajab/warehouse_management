@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
      <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.product.add_your_product') }}</h1>
        </div>
      </div>
    </div>
    <form class="" action="{{route('customer_products.store')}}" method="POST" enctype="multipart/form-data" id="choice_form">
        @csrf
        <input type="hidden" name="added_by" value="{{ Auth::user()->user_type }}">
        <input type="hidden" name="status" value="available">

        <!-- General -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.general')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.product.product_name')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" name="name" placeholder="{{ __('storefront.product.product_name')}}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.product.product_category')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <select class="form-control aiz-selectpicker" data-placeholder="{{ __('storefront.product.select_a_category')}}" id="categories" name="category_id" data-live-search="true" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                                @foreach ($category->childrenCategories as $childCategory)
                                    @include('shop.categories.child_category', ['child_category' => $childCategory])
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.product.product_brand')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <select class="form-control aiz-selectpicker" data-placeholder="{{ __('storefront.product.select_a_brand')}}" data-live-search="true"  id="brands" name="brand_id">
                            <option value=""></option>
                            @foreach (\App\Models\Brand::all() as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.product.product_unit')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" name="unit" placeholder="{{ __('storefront.product.product_unit')}}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.condition')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <select class="form-control selectpicker" data-placeholder="{{ __('storefront.common.select_a_condition')}}" id="conditon" name="conditon" required>
                            <option value="new">{{ __('storefront.common.new')}}</option>
                            <option value="used">{{ __('storefront.common.used')}}</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.location')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" name="location" placeholder="{{ __('storefront.common.location')}}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{ __('storefront.product.product_tag')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input type="text" class="form-control aiz-tag-input rounded-0" name="tags[]" placeholder="{{ __('storefront.common.type_hit_enter')}}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.images')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.gallery_images')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="photos" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.thumbnail_image')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="thumbnail_img" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Videos -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.videos')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.video_from')}}</label>
                    <div class="col-md-10">
                        <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity" name="video_provider">
                            <option value="youtube">{{ __('storefront.common.youtube')}}</option>
                            <option value="dailymotion">{{ __('storefront.common.dailymotion')}}</option>
                            <option value="vimeo">{{ __('storefront.common.vimeo')}}</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.video_url')}}</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control rounded-0" name="video_link" placeholder="{{ __('storefront.common.video_link')}}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Meta Image -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.meta_tags')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.meta_title')}}</label>
                    <div class="col-md-10">
                        <input type="text" name="meta_title" class="form-control rounded-0" placeholder="{{ __('storefront.common.meta_title')}}">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{__('storefront.common.description')}}</label>
                    <div class="col-md-10">
                        <textarea name="meta_description" rows="8" class="form-control rounded-0"></textarea>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{ __('storefront.common.meta_image')}}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="meta_img" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.product.price')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{ __('storefront.product.unit_price')}} <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input type="number" lang="en" min="0" step="0.01" class="form-control rounded-0" name="unit_price" placeholder="{{ __('storefront.product.unit_price')}} ({{ __('storefront.product.base_price')}})" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.description')}} <span class="text-danger">*</span></h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{ __('storefront.common.description')}}</label>
                    <div class="col-md-10">
                        <div class="mb-3">
                            <textarea class="aiz-text-editor rounded-0" name="description" required></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PDF Specification -->
        <div class="card rounded-0 shadow-none border">
            <div class="card-header border-bottom-0">
                <h5 class="mb-0 fs-18 fw-700 text-dark">{{__('storefront.common.pdf_specification')}}</h5>
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-2 col-from-label">{{ __('storefront.common.pdf')}}</label>
                    <div class="col-md-10">
                        <div class="input-group" data-toggle="aizuploader" data-type="document">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="pdf" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="mar-all text-right">
            <button type="submit" name="button" class="btn btn-primary rounded-0 px-4">{{ __('storefront.product.save_product') }}</button>
        </div>
    </form>

@endsection
