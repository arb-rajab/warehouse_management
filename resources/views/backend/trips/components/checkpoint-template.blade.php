<!-- Order Checkpoint Template -->
<div id="order-checkpoint-template" class="checkpoint order-checkpoint px-2" style="display: none">
    <hr class="custom-separator">
    <div class="row my-2 align-items-center">
        <div class="col-sm-1 text-center drag-handle" style="cursor: grab;">⇅</div>
        <input type="hidden" name="checkpoints_array[][id]" value="0">

        <div class="col-sm">
            <label class="col-from-label">{{ translate('Type') }}</label>
            <span class="form-control">{{ translate('Delivery') }}</span>
            <input type="hidden" name="checkpoints_array[][checkpoint_type]" value="delivery">
        </div>

        <div class="col-sm">
            <label class="col-from-label required">{{ translate('Order') }}
                <span class="text-danger">*</span>
            </label>
            <select class="form-control order-id-input" name="checkpoints_array[][checkpoint_related_id]" data-live-search="true" required>
                <option value="">{{ translate('Select Order') }}</option>
                @foreach (\App\Models\Order::whereIn('delivery_status', ['on_delivery'])->orderBy('code', 'desc')->get() as $order)
                    <option value="{{ $order->id }}" >
                        {{ '#' . $order->code . ' ' . $order->customer?->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-sm">
            <label class="col-from-label">{{ translate('Notes') }}</label>
            <textarea class="form-control" name="checkpoints_array[][checkpoint_notes]" placeholder="{{ translate('Enter notes') }}"></textarea>
        </div>

        <div class="col-sm-auto text-center">
            <button type="button" class="remove-checkpoint btn btn-danger action-btn">{{ translate('Remove') }}</button>
        </div>
    </div>
    <hr class="custom-separator">
</div>

<!-- Customer Checkpoint Template -->
<div id="customer-checkpoint-template" class="checkpoint customer-checkpoint px-2" style="display: none">
    <hr class="custom-separator">
    <div class="row my-2 align-items-center">
        <div class="col-sm-1 text-center drag-handle" style="cursor: grab;">⇅</div>
        <input type="hidden" name="checkpoints_array[][id]" value="0">

        <div class="col-sm">
            <label class="col-from-label">{{ translate('Type') }}</label>
            <span class="form-control">{{ translate('Other') }}</span>
            <input type="hidden" name="checkpoints_array[][checkpoint_type]" value="other">
        </div>

        <div class="col-sm">
            <label class="col-from-label required">{{ translate('Customer') }}
                <span class="text-danger">*</span>
            </label>
            <select class="form-control user-id-input" name="checkpoints_array[][checkpoint_related_id]" data-live-search="true" required>
                <option value="">{{ translate('Select Customer') }}</option>
                @foreach (\App\Models\User::where('is_rep', false)->orderBy('name', 'desc')->get() as $customer)
                    <option value="{{ $customer->id }}">
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-sm">
            <label class="col-from-label required">{{ translate('Notes') }}
                <span class="text-danger">*</span>
            </label>
            <textarea class="form-control" name="checkpoints_array[][checkpoint_notes]" placeholder="{{ translate('Enter notes') }}" required></textarea>
        </div>

        <div class="col-sm-auto text-center">
            <button type="button" class="remove-checkpoint btn btn-danger action-btn">{{ translate('Remove') }}</button>
        </div>
    </div>
    <hr class="custom-separator">
</div>
