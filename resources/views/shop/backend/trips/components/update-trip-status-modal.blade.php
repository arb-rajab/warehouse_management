<!-- update status Modal -->
<div id="update-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{ translate('Update Trip Status') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="trip-id">
                <select id="update-status" class="form-control">
                    @foreach (\App\Enums\TripStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ translate($status->name) }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-secondary rounded-0 mt-2"
                    data-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" class="btn btn-primary rounded-0 mt-2"
                    onclick="update_status()">{{ translate('Update') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    function update_status(id) {
        var updatedStatus = document.getElementById('update-status').value;
        var tripId = document.getElementById('trip-id').value;
        $('#update-modal').modal('hide');
        $.post('{{ route('trips.update-status') }}', {
            _token: '{{ csrf_token() }}',
            id: tripId,
            status: updatedStatus
        }, function(data) {
            if (data == 1) {
                AIZ.plugins.notify('success', '{{ translate('status updated successfully') }}');
                var updateButton = document.querySelector('[data-trip-id="' + tripId + '"]');
                updateButton.setAttribute('data-old-status', updatedStatus);
                $('#trip-status-' + tripId).text(updatedStatus);
            } else {
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }
        });
    }

    function openUpdateModal(button) {
        var tripId = button.getAttribute('data-trip-id');
        var oldStatus = button.getAttribute('data-old-status');
        document.getElementById('update-status').value = oldStatus;
        document.getElementById('trip-id').value = tripId;
        $('#update-modal').modal('show');
    }
</script>
