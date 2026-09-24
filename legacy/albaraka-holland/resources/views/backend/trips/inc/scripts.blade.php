<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script type="text/javascript">
    $(document).ready(function() {

        // for edit form
        var selectedDate = $('#due_date').val();
        if (selectedDate) {
            fetchAvailableVehicles(selectedDate, "{{ $trip?->id ?? 0 }}");
        }

        const checkpointsContainer = $("#checkpoints");
        let checkpointIndex = {{ isset($key) ? $key + 1 : 0 }}; // Initialize a counter for dynamic indexing

        // Function to add a checkpoint
        function addCheckpoint(templateId, checkpointType) {
            const newCheckpoint = $("#" + templateId).clone(); // Clone the template
            newCheckpoint.show(); // Show the cloned checkpoint
            newCheckpoint.find("textarea, select").val(""); // Clear input values
            // Set the dynamic name attributes
            newCheckpoint.find("input[name='checkpoints_array[][id]']").attr('name', `checkpoints_array[${checkpointIndex}][id]`);
            newCheckpoint.find("input[name='checkpoints_array[][checkpoint_type]']").attr('name', `checkpoints_array[${checkpointIndex}][checkpoint_type]`);
            newCheckpoint.find("select[name='checkpoints_array[][checkpoint_related_id]']").attr('name', `checkpoints_array[${checkpointIndex}][checkpoint_related_id]`);
            newCheckpoint.find("textarea[name='checkpoints_array[][checkpoint_notes]']").attr('name', `checkpoints_array[${checkpointIndex}][checkpoint_notes]`);
            checkpointsContainer.append(newCheckpoint); // Append the new checkpoint
            checkpointIndex++; // Increment the counter for the next checkpoint
            updateCheckpointStyles(); // Update styles after adding
        }

        // Add new customer checkpoint
        $("#add-customer-checkpoint").click(function() {
            addCheckpoint("customer-checkpoint-template", "other");
        });

        // Add new order checkpoint
        $("#add-order-checkpoint").click(function() {
            addCheckpoint("order-checkpoint-template", "delivery");
        });

        // Remove a checkpoint
        $(document).on("click", ".remove-checkpoint", function() {
            $(this).closest(".checkpoint").remove(); // Remove the clicked checkpoint
            updateCheckpointStyles(); // Update styles after removal
        });

        // Remove hidden checkpoints before form submission
        $("form").on("submit", function() {
            $(".checkpoint:hidden").remove(); // Remove hidden checkpoints before form submission
        });

        // Initialize SortableJS with jQuery
        new Sortable(checkpointsContainer[0], {
            multiDrag: true, // Enable multi-drag
            selectedClass: 'item-selected', // Class applied to selected items
            animation: 150,
            handle: '.drag-handle', // Only allow dragging using the handle
            onEnd: function() {
                console.log('Order changed!');
                updateCheckpointStyles(); // Reapply styles after sorting
            },
            onSelect: function(evt) {
                $(evt.item).css("background-color", "#95cff1"); // Highlight selected item
            },
            onDeselect: function(evt) {
                $(evt.item).css("background-color", ""); // Remove highlight from deselected item
            },
        });

        // Function to refresh styles for even checkpoints
        const updateCheckpointStyles = () => {
            checkpointsContainer.children(".checkpoint").each(function(index) {
                if ((index + 1) % 2 === 0) {
                    $(this).css("background-color", "#f8f9fa"); // Light grey for even checkpoints
                } else {
                    $(this).css("background-color", ""); // Reset for odd checkpoints
                }
            });
        };

        // Initial style update
        updateCheckpointStyles();

        // Trigger when the due date is changed
        $('#due_date').on('change', function() {
            var selectedDate = $(this).val();

            if (selectedDate) {
                // Make an AJAX call to fetch available drivers and trucks
                fetchAvailableVehicles(selectedDate, "{{ $trip?->id ?? 0 }}");
            }
        });

        // Function to fetch available drivers and trucks
        function fetchAvailableVehicles(date, excluded_trip_id = 0) {
            $.ajax({
                url: '{{ route("getAvailableVehicles") }}',  // Your route to get available vehicles
                method: 'GET',
                data: {
                    date: date,
                    excluded_trip_id: excluded_trip_id,
                },
                dataType: 'json',
                success: function(data) {
                    updateDriverOptions(data.drivers);
                    updateTruckOptions(data.trucks);

                    // Check if no drivers and trucks are available
                    if ((!data.drivers || data.drivers.length === 0) || (!data.trucks || data.trucks.length === 0)) {
                        $('#due-date-hint').show(); // Show the hint
                    } else {
                        $('#due-date-hint').hide(); // Hide the hint if records are available
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching available vehicles:', error);
                    $('#due-date-hint').show(); // Show the hint on error
                }
            });
        }

        // Update driver options
        function updateDriverOptions(drivers) {
            var driverSelect = $('#driver_id');
            var driverHint = $('#driver-hint'); // Hint placeholder

            driverSelect.empty();  // Clear the existing options
            driverSelect.append('<option value="">{{ translate('Select Driver') }}</option>');  // Default option

            if (drivers && drivers.length > 0) {
                driverHint.hide(); // Hide hint if drivers exist
                var selectedDriverId = '{{ old("driver_id", $trip?->driver_id) }}';

                $.each(drivers, function(index, driver) {
                    var selected = (driver.id == selectedDriverId) ? 'selected' : '';
                    driverSelect.append(
                        '<option value="' + driver.id + '" ' + selected + '>' + driver.name + '</option>'
                    );
                });
            } else {
                driverHint.show(); // Show hint if no drivers are available
            }

            driverSelect.selectpicker('refresh'); // Refresh the selectpicker
        }

        // Update truck options
        function updateTruckOptions(trucks) {
            var truckSelect = $('#truck_id');
            var truckHint = $('#truck-hint'); // Hint placeholder

            truckSelect.empty();  // Clear the existing options
            truckSelect.append('<option value="">{{ translate('Select Truck') }}</option>');  // Default option

            if (trucks && trucks.length > 0) {
                truckHint.hide(); // Hide hint if trucks exist
                var selectedTruckId = '{{ old("truck_id", $trip?->truck_id) }}';

                $.each(trucks, function(index, truck) {
                    var selected = selectedTruckId == truck.id ? 'selected' : '';

                    truckSelect.append('<option value="' + truck.id + '" ' + selected + '>' + truck.name + ' ' + truck.model + '</option>');
                });
            } else {
                truckHint.show(); // Show hint if no trucks are available
            }

            truckSelect.selectpicker('refresh'); // Refresh the selectpicker
        }
    });
    // delete templates checkpoints
    function deleteTemplatesCheckpoints() {
        $(".checkpoint:hidden").remove(); // Remove hidden checkpoints before form submission
    }
</script>
