(function () {
    const form = document.getElementById('digital-product-form');
    if (!form) return;
    const price = form.querySelector('[name="unit_price"]');
    const discount = form.querySelector('[name="discount"]');
    const kind = form.querySelector('[name="discount_type"]');
    function discountLimit() {
        discount.max = kind.value === 'percent' ? '100' : price.value;
    }
    price.addEventListener('input', discountLimit);
    kind.addEventListener('change', discountLimit);
    discountLimit();
    form.addEventListener('submit', function (event) {
        const file = form.querySelector('[name="file"]');
        const error = form.querySelector('[data-digital-file-error]');
        error.hidden = Boolean(file.value);
        error.textContent = file.value ? '' : error.dataset.message;
        if (!file.value) {
            event.preventDefault();
            file.closest('[data-toggle="aizuploader"]').focus();
        }
    });
})();
