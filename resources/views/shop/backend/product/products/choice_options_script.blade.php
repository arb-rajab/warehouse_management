<script>
    function appendProductAttributeChoice(attributeId, attributeName, values) {
        var row = document.createElement('div');
        row.className = 'form-group row';
        var titleColumn = document.createElement('div');
        titleColumn.className = 'col-md-3';
        var number = document.createElement('input');
        number.type = 'hidden';
        number.name = 'choice_no[]';
        number.value = attributeId;
        var title = document.createElement('input');
        title.type = 'text';
        title.className = 'form-control';
        title.name = 'choice[]';
        title.value = attributeName;
        title.placeholder = @json(translate('Choice Title'));
        title.readOnly = true;
        titleColumn.append(number, title);
        var valueColumn = document.createElement('div');
        valueColumn.className = 'col-md-8';
        var select = document.createElement('select');
        select.className = 'form-control aiz-selectpicker attribute_choice';
        select.dataset.liveSearch = 'true';
        select.name = 'choice_options_' + attributeId + '[]';
        select.multiple = true;
        values.forEach(function (value) {
            select.add(new Option(value.value, value.value));
        });
        valueColumn.append(select);
        row.append(titleColumn, valueColumn);
        document.getElementById('customer_choice_options').append(row);
    }
</script>
