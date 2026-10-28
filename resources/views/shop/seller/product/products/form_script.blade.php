@section('script')
    <template id="shipping-required-mark"><span data-shipping-required><x-required-mark /></span></template>
    <template id="choice-required-mark"><x-required-mark /></template>
    <script type="text/javascript">
        function shippingRequirement() {
            const required = $('[name=shipping_type]').val() === 'flat_rate';
            const field = $('#seller-product-flat_shipping_cost');
            const label = $('label[for="seller-product-flat_shipping_cost"]');
            field.prop('required', required).attr('aria-required', String(required));
            label.find('[data-shipping-required]').remove();
            if (required) label.append(document.getElementById('shipping-required-mark').content.cloneNode(true));
        }
        $('[name=shipping_type]').on('change', shippingRequirement);
        shippingRequirement();

        function refreshChoiceButtons() {
            const rows = $('#customer_choice_options [data-choice-row]');
            rows.each(function(index) {
                $(this).find('[data-choice-move=-1]').prop('disabled', index === 0);
                $(this).find('[data-choice-move=1]').prop('disabled', index === rows.length - 1);
            });
        }
        refreshChoiceButtons();
        $(document).on('click', '[data-choice-move]', function() {
            const row = $(this).closest('[data-choice-row]');
            const up = Number($(this).attr('data-choice-move')) < 0;
            const target = up ? row.prev('[data-choice-row]') : row.next('[data-choice-row]');
            if (!target.length) return;
            if (up) row.insertBefore(target); else row.insertAfter(target);
            refreshChoiceButtons();
            update_sku();
        });

        let choiceRevision = 0;
        function add_more_customer_choice_option(i, name, revision) {
            return $.ajax({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                type: 'POST', dataType: 'json',
                url: '{{ route('seller.products.add-more-choice-option') }}',
                data: { attribute_id: i },
                success: function(options) {
                    if (revision !== choiceRevision) return;
                    const row = $('<div class="form-group row" data-choice-row><div class="col-md-3"><input type="hidden" name="choice_no[]"><label></label><div><button type="button" data-choice-move="-1">↑</button><button type="button" data-choice-move="1">↓</button></div></div><div class="col-md-8"><select class="form-control aiz-selectpicker attribute_choice" multiple required aria-required="true"></select></div></div>');
                    row.find('input').val(i);
                    row.find('[data-choice-move=-1]').attr('aria-label', {{ \Illuminate\Support\Js::from(__('seller.products.move_attribute_up_label', ['name' => ':name'])) }}.replace(':name', () => name));
                    row.find('[data-choice-move=1]').attr('aria-label', {{ \Illuminate\Support\Js::from(__('seller.products.move_attribute_down_label', ['name' => ':name'])) }}.replace(':name', () => name));
                    row.find('label').attr('for', 'choice-options-' + i).text(name).append(document.getElementById('choice-required-mark').content.cloneNode(true));
                    row.find('select').attr('id', 'choice-options-' + i).attr('name', 'choice_options_' + i + '[]').html(options);
                    $('#customer_choice_options').append(row);
                    refreshChoiceButtons();
                    AIZ.plugins.bootstrapSelect('refresh');
                }
            });
        }

        $('input[name="colors_active"]').on('change', function() {
            if (!$('input[name="colors_active"]').is(':checked')) {
                $('#colors').prop('disabled', true);
                AIZ.plugins.bootstrapSelect('refresh');
            } else {
                $('#colors').prop('disabled', false);
                AIZ.plugins.bootstrapSelect('refresh');
            }
            update_sku();
        });

        $(document).on("change", ".attribute_choice", function() {
            update_sku();
        });

        $('#colors').on('change', function() {
            update_sku();
        });

        $('input[name="unit_price"]').on('change', function() {
            update_sku();
        });

        // $('input[name="name"]').on('keyup', function() {
        //     update_sku();
        // });

        function delete_row(em) {
            $(em).closest('.form-group row').remove();
            update_sku();
        }

        function delete_variant(em) {
            $(em).closest('.variant').remove();
        }

        let previewRevision = 0;
        function update_sku() {
            const revision = ++previewRevision;
            $.ajax({
                type: 'POST',
                url: '{{ $editing ? route('seller.products.sku_combination_edit') : route('seller.products.sku_combination') }}',
                data: $('#choice_form').serializeArray().concat(@json($editing ? [['name' => 'id', 'value' => (string) $product->id]] : [])).filter(function(field) { return /^(?:_token|id|name|unit_price|colors_active|colors\[\]|choice_no\[\]|choice_options_\d+\[\]|(?:price|sku|qty|img)_.+)$/.test(field.name); }),
                success: function(data) {
                    if (revision !== previewRevision) return;
                    $('#variant-preview-error').remove();
                    $('#sku_combination').html(data);
                    AIZ.plugins.fooTable();
                    $('#show-hide-div').toggle($('#sku_combination .variant').length === 0);
                },
                error: function() {
                    if (revision !== previewRevision) return;
                    $('#variant-preview-error').remove();
                    $('<div id="variant-preview-error" class="alert alert-danger" role="alert"></div>').text({{ \Illuminate\Support\Js::from(__('seller.products.could_not_generate_variants_check_the_selected_options')) }}).insertBefore('#sku_combination');
                }
            });
        }

        $('#choice_attributes').on('change', async function() {
            const revision = ++choiceRevision;
            $('#customer_choice_options').empty();
            for (const option of $('#choice_attributes option:selected').toArray()) {
                if (revision !== choiceRevision) return;
                await add_more_customer_choice_option($(option).val(), $(option).text(), revision);
            }
            if (revision === choiceRevision) update_sku();
        });
    </script>
@endsection
