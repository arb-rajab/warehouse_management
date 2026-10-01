<!-- update status Modal -->
<div id="update-modal" class="modal fade">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h6">{{ translate('Update Checkpoint Status') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="checkpoint-id">
                <select id="update-status" class="form-control">
                    @foreach (\App\Enums\CheckpointStatus::cases() as $status)
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
</div><!-- /.modal -->

<script>
    function update_status(id) {
        var updatedStatus = document.getElementById('update-status').value;
        var checkpointId = document.getElementById('checkpoint-id').value;
        $('#update-modal').modal('hide');
        $.post('{{ route('checkpoints.update-status') }}', {
            _token: '{{ csrf_token() }}',
            id: checkpointId,
            status: updatedStatus
        }, function(data) {
            if (data == 1) {
                AIZ.plugins.notify('success', '{{ translate('status updated successfully') }}');
                var updateButton = document.querySelector('[data-checkpoint-id="' + checkpointId + '"]');
                updateButton.setAttribute('data-old-status', updatedStatus);
                $('#checkpoint-status-' + checkpointId).text(updatedStatus);
            } else {
                AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
            }
        });
    }

    function openUpdateModal(button) {
        var checkpointId = button.getAttribute('data-checkpoint-id');
        var oldStatus = button.getAttribute('data-old-status');
        document.getElementById('update-status').value = oldStatus;
        document.getElementById('checkpoint-id').value = checkpointId;
        $('#update-modal').modal('show');
    }
</script>
