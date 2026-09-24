<table class="table table-bordered aiz-table">
    <thead>
        <tr>
            <td width="50%">
                <span>{{ translate('Brand') }}</span>
            </td>
            <td data-breakpoints="lg">
                <span>{{ translate('Amount Required') }}</span>
            </td>
        </tr>
    </thead>
    <tbody>
        @php
            $brand = \App\Models\Brand::findOrFail($brand_id);
        @endphp
        <tr>
            <td>
                <div class="from-group row">
                    <div class="col">
                        <span>{{ $brand->name }}</span>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" lang="en" name="brand_amount_required" min="0"
                    step="1" class="form-control" required>
            </td>
        </tr>
    </tbody>
</table>
