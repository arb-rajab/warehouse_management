<!-- Order Checkpoint Template -->
@if ($type == 'delivery')
    <div id="order-checkpoint-template" class="checkpoint order-checkpoint px-2">
        <hr class="custom-separator">
        <div class="row my-2 align-items-center">
            <div class="col-sm-1 text-center drag-handle" style="cursor: grab;">⇅</div>
            <input type="hidden" name="checkpoints_array[{{ $key }}][id]"
                value="{{ old("checkpoints_array.$key.id", $checkpoint->id ?? 0) }}">

            <div class="col-sm">
                <label class="col-from-label">{{ translate('Type') }}</label>
                <span class="form-control">{{ translate('Delivery') }}</span>
                <input type="hidden" name="checkpoints_array[{{ $key }}][checkpoint_type]" value="delivery">
            </div>

            <div class="col-sm">
                <label class="col-from-label required">{{ translate('Order') }}
                    <span class="text-danger">*</span>
                </label>
                <select class="form-control order-id-input" name="checkpoints_array[{{ $key }}][checkpoint_related_id]"
                    data-live-search="true" required>
                    <option value="">{{ translate('Select Order') }}</option>
                    @foreach (\App\Models\Order::whereIn('delivery_status', ['on_delivery'])->orderBy('code', 'desc')->get() as $order)
                        <option value="{{ $order->id }}"
                            {{ old("checkpoints_array.$key.checkpoint_related_id", $checkpoint->relationable_id ?? '') == $order->id ? 'selected' : '' }}>
                            {{ '#' . $order->code . ' ' . $order->customer?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-sm">
                <label class="col-from-label">{{ translate('Notes') }}</label>
                <textarea class="form-control" name="checkpoints_array[{{ $key }}][checkpoint_notes]"
                    placeholder="{{ translate('Enter notes') }}">{{ old("checkpoints_array.$key.checkpoint_notes", $checkpoint->notes ?? '') }}</textarea>
            </div>

            <div class="col-sm-auto text-center">
                <button type="button"
                    class="remove-checkpoint btn btn-danger action-btn">{{ translate('Remove') }}</button>
            </div>
        </div>
        <hr class="custom-separator">
    </div>
@endif

@if ($type == 'other')
    <div id="customer-checkpoint-template" class="checkpoint customer-checkpoint px-2">
        <hr class="custom-separator">
        <div class="row my-2 align-items-center">
            <div class="col-sm-1 text-center drag-handle" style="cursor: grab;">⇅</div>
            <input type="hidden" name="checkpoints_array[{{ $key }}][id]" value="{{ $checkpoint?->id }}">

            <div class="col-sm">
                <label class="col-from-label">{{ translate('Type') }}</label>
                <span class="form-control">{{ translate('Other') }}</span>
                <input type="hidden" name="checkpoints_array[{{ $key }}][checkpoint_type]" value="other">
            </div>

             <div class="col-sm">
                <label class="col-from-label required">{{ translate('Customer') }}
                    <span class="text-danger">*</span>
                </label>
              <select class="form-control user-id-input selectpicker"
        name="checkpoints_array[{{ $key }}][checkpoint_related_id]"
        data-live-search="true"
        title="{{ translate('Select Customer') }}"
        data-none-results-text="لا يوجد نتائج"
        data-size="5" {{-- عرض فقط 5 عناصر عند البحث --}}
        required>
    @foreach (\App\Models\User::where('is_rep', false)->orderBy('name', 'desc')->get() as $customer)
        <option 
            value="{{ $customer->id }}" 
            {{ old("checkpoints_array.$key.checkpoint_related_id", $checkpoint->relationable_id ?? '') == $customer->id ? 'selected' : '' }}>
            {{ $customer->name }}
        </option>
    @endforeach
</select>

<script>
    $(document).ready(function () {
        $('.selectpicker').selectpicker();

        $('.user-id-input').on('show.bs.select', function (e) {
            let searchBox = $(this).parent().find('.bs-searchbox input');
            
            if (!searchBox.val()) {
                e.preventDefault(); 
            }

            searchBox.on('input', () => {
                $(this).selectpicker('toggle'); 
                $(this).selectpicker('toggle');
            });
        });
    });
</script>


            </div>


            <div class="col-sm">
                <label class="col-from-label required">{{ translate('Notes') }}
                    <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" name="checkpoints_array[{{ $key }}][checkpoint_notes]"
                    placeholder="{{ translate('Enter notes') }}" required>{{ old("checkpoints_array.$key.checkpoint_notes", $checkpoint->notes ?? '') }}</textarea>
            </div>

            <div class="col-sm-auto text-center">
                <button type="button"
                    class="remove-checkpoint btn btn-danger action-btn">{{ translate('Remove') }}</button>
            </div>
        </div>
        <hr class="custom-separator">
    </div>
@endif
