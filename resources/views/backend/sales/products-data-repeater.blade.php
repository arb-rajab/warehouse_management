<!-- Product Template (Hidden) -->
<div id="product-template" class="product px-2 d-none">
    <hr class="custom-separator">
    <div class="row my-2 align-items-center">
        <div class="col-sm-1 text-center drag-handle" style="cursor: grab;">⇅</div>
        <input type="hidden" name="products_array[][id]" value="0">

        <div class="col-sm">
            <label class="col-form-label">{{ translate('Product') }}</label>
            <select class="form-control product-id-input" name="products_array[][product_related_id]"
                data-live-search="true" required>
                <option value="">{{ translate('Select product') }}</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ '#' . $product->code . ' ' . $product->customer?->name }}
                    </option>
                @endforeach
            </select>
            <div class="form-group row mt-2">
                <div class="col-auto">
                    <img class="size-60px img-fit" src="" alt="Product Image" data-placeholder>
                </div>
                <div class="col">
                    <span data-placeholder>{{ translate('Product Name') }}</span>
                </div>
            </div>
            <span class="form-control" data-placeholder>{{ translate('Unit Price') }}</span>
            <input type="number" lang="en" name="edited_price_[]" min="0" step="0.1"
                class="form-control" placeholder="{{ translate('Edited Price') }}">
        </div>

        <div class="col-sm">
            <label class="col-form-label">{{ translate('Notes') }}</label>
            <textarea class="form-control" name="products_array[][product_notes]" placeholder="{{ translate('Enter notes') }}"></textarea>
        </div>

        <div class="col-sm-auto text-center">
            <button type="button" class="remove-product btn btn-danger action-btn">{{ translate('Remove') }}</button>
        </div>
    </div>
    <hr class="custom-separator">
</div>


<script>
    $(document).ready(function() {
        const productsContainer = $("#products");
        let productIndex = productsContainer.children(".product").length;

        function addProduct() {
            const newProduct = $("#product-template").clone().removeAttr("id").removeClass("d-none");
            newProduct.find("input, select, textarea").each(function() {
                let name = $(this).attr("name");
                if (name) {
                    name = name.replace("[]", `[${productIndex}]`);
                    $(this).attr("name", name);
                }
            });
            productsContainer.append(newProduct);
            productIndex++;
            updateProductStyles();
        }

        $("#add-product").click(function() {
            addProduct();
        });

        $(document).on("click", ".remove-product", function() {
            $(this).closest(".product").remove();
            updateProductStyles();
        });

        $(".sortable-products").sortable({
            handle: ".drag-handle",
            update: function() {
                updateProductStyles();
            }
        });

        function updateProductStyles() {
            $(".product").each(function(index) {
                $(this).css("background-color", index % 2 === 0 ? "#f8f9fa" : "");
            });
        }
    });
</script>
