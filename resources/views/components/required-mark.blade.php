{{--
    The mark after a required field's label (.ai/rules/form-required-fields.md):
    a red asterisk hidden from assistive technology, plus visually hidden
    "required" text. Use it only when the control carries `required` and the
    server rule is `required`. The red is fixed (#b91c1c, 6.5:1 on white): the
    theme's .text-danger is too light for text (3.6:1).

        <label for="name">{{ translate('Full Name') }}<x-required-mark /></label>
        <input id="name" name="name" required>
--}}
<span style="color: #b91c1c" data-required-mark><span aria-hidden="true">*</span><span class="sr-only">{{ translate('required') }}</span></span>
