---
paths:
  - 'resources/js/lib/formValidation.ts'
  - 'resources/js/lib/formValidationNamedRules.ts'
  - 'resources/js/lib/useFormValidation.ts'
  - 'resources/js/lib/storeProductForm.ts'
  - 'resources/js/lib/storeProductRules.json'
  - 'resources/js/components/ResourceFormPage.vue'
  - 'resources/js/components/StoreProductFormFields.vue'
  - 'resources/js/components/StoreProductVariantFields.vue'
  - 'resources/js/components/StoreDigitalProductFormFields.vue'
  - 'resources/js/pages/Store/Admin/Product/Products/Create.vue'
  - 'resources/js/pages/Store/Admin/Product/Products/Edit.vue'
  - 'resources/js/pages/Store/Admin/Product/DigitalProducts/Create.vue'
  - 'resources/js/pages/Store/Admin/Product/DigitalProducts/Edit.vue'
  - 'app/Http/Requests/Concerns/ValidatesStoreProduct.php'
  - 'app/Http/Requests/Concerns/ValidatesStoreDigitalProduct.php'
  - 'app/Http/Requests/Concerns/ValidatesStoreProductVariants.php'
  - 'app/Http/Requests/Concerns/ValidatesStoreProductTaxes.php'
  - 'app/Http/Requests/Concerns/ValidatesDiscountDateRange.php'
  - 'app/Http/Requests/Store/Admin/StoreProductRequest.php'
  - 'app/Http/Requests/Store/Admin/UpdateProductRequest.php'
  - 'app/Http/Requests/Store/Admin/StoreDigitalProductRequest.php'
  - 'app/Http/Requests/Store/Admin/UpdateDigitalProductRequest.php'
  - 'lang/en/validation.php'
  - 'lang/ar/validation.php'
  - 'tests/Feature/Store/Admin/ProductClientValidationParityTest.php'
  - 'resources/js/lib/warehouseForm.ts'
  - 'resources/js/lib/warehouseRules.json'
  - 'resources/js/pages/Admin/Warehouses/{Create,Edit}.vue'
  - 'tests/Feature/Admin/WarehouseClientValidationParityTest.php'
  - 'resources/js/lib/palletTransferForm.ts'
  - 'resources/js/lib/palletTransferRules.json'
  - 'resources/js/components/PalletTransferFields.vue'
  - 'resources/js/components/PalletTransferDialog.vue'
  - 'resources/js/components/PalletActionsDialog.vue'
  - 'tests/Feature/Admin/TransferPalletClientValidationParityTest.php'
  - 'resources/js/lib/smtpSettingsForm.ts'
  - 'resources/js/lib/smtpSettingsRules.json'
  - 'resources/js/pages/Store/Admin/Settings/Smtp.vue'
  - 'app/Http/Requests/Store/Admin/UpdateSmtpSettingsRequest.php'
  - 'tests/Feature/Store/Admin/SmtpSettingsClientValidationParityTest.php'
---

# Forms validate in the browser first

Added 2026-11-05. Every mistake the browser can see is reported before a
request is sent, from a copy of the server's rules; **the backend remains the
authority** and is never removed or weakened for it. New or changed server
rules update the client copy in the same change, and every new form should
adopt the layer. The physical and digital product create/edit forms and the WMS
warehouse create/edit forms use it today; other forms adopt it one at a time
(the warehouse copy is `lib/warehouseRules.json`, checked by
`WarehouseClientValidationParityTest`). The admin pallet transfer (the row
page's pallet dialog, transfer tab, and the warehouse map's transfer dialog)
uses `lib/palletTransferRules.json`, checked by
`TransferPalletClientValidationParityTest`: the `exists` rules stay on the
server, and `return_to`/`warehouse_ids` have no control. Those dialogs are not
`ResourceFormPage`s: they call `useFormValidation` themselves (its handlers on
the `<form>`, `onBefore()` before `form.submit`, `onStart` on the visit) and
read inline errors through `errorsFor(form.errors)`; the banner shows only the
errors no field shows (`nonFieldErrors`). The store SMTP settings form uses
`lib/smtpSettingsRules.json`, checked by `SmtpSettingsClientValidationParityTest`;
its `serverOnly` list holds `email:rfc` (the engine has no email rule), and the
request's `after()` checks read the saved settings, so they stay on the server.

## The layer

- `lib/formValidation.ts`: the engine. A `ValidationSchema` maps each field
  (dot notation, `*` for any row: `variants.*.price`) to Laravel rule tokens in
  the server's order (`required`, `max:200`, `lte:max_qty`,
  `required_if:shipping_type,flat_rate`, `in:a,b`, `distinct`, ...); `named`
  adds copies of server closures. It reads the `<form>` as PHP parses the post
  (`a[]` rows, the last of repeated names, trimmed, blank as null), runs
  Laravel's order (an absent field runs only the required rules; a null
  `nullable` one skips the rest; the first failure wins) and words the message
  with Laravel's placeholders. `supportedTokens` lists what it checks.
- `lib/formValidationNamedRules.ts`: the copies of server closures, by name
  (`discountLimit`, `discountDateRange`), each with the fields it reads.
- `lib/useFormValidation.ts`: the behaviour. A field is checked when it is
  left or changed; once it shows an error, or after a submit, it follows its
  value as it is typed. Fields that read another (`required_if`, `lte`, a named
  rule's `dependsOn`) are checked again when that one changes. A submit checks
  every field; with an error nothing is sent (`<Form>`'s `onBefore` returns
  false), the first visible invalid control is scrolled to and focused, and
  `aria-invalid` marks the invalid controls. Server errors still show, each
  until its field is edited, and a new request starts clean.
- `ResourceFormPage`'s optional `validation` prop turns it on: the form gets
  `novalidate` (inline messages replace the native bubbles), the messages
  reach the fields through the same `errors` slot prop as the server's
  (`FieldError` styling), and a `role="alert"` line above the buttons
  (`common.formValidation.blocked`) says the submit was held back.
- Messages are the server's: `resources/js/locales/{en,ar}.json` under
  `validation.messages` and `validation.attributes` repeat
  `lang/{en,ar}/validation.php` word for word (Arabic falls back to English
  where a line is missing, as Laravel does). Never reword them in the JSON:
  change the PHP line and copy it.

## The rule copy and its parity test

- A form's rules live as data the browser and Pest both read. For the
  products: `lib/storeProductRules.json` (`physical`/`digital`: shared
  `rules`, `create`/`edit` extras, `named`, `readOnlyNullable`), composed by
  `physicalProductValidation()`/`digitalProductValidation()` in
  `lib/storeProductForm.ts`. A field the business settings hide has
  `{ "when": "<section>", "rules": [...] }` (the server `exclude`s it); a
  read-only Otajer field becomes `['nullable']`, as
  `UpdateProductRequest::readOnlyFieldRules()` swaps it
  ([product-field-sources.md](product-field-sources.md)).
- `tests/Feature/Store/Admin/ProductClientValidationParityTest.php` builds
  each FormRequest's `rules()` (sections on and off, a store-origin and a
  feed-origin product) and fails unless: the client lists exactly the server's
  fields; each field's client tokens equal the server's client-checkable
  tokens in order; every other server rule is a named client rule (a closure)
  or on `productServerOnlyRules()` with a reason (exists, unique, upload
  ownership, the free slug, database-fed `in` lists, `size`/`list`/`array:keys`
  shapes, input-dependent `variants` presence; `after()` hooks are server-only
  by construction); each named rule exists in the registry; the engine's
  `supportedTokens` match the test's list; and the mirrored messages equal the
  lang lines in both locales.
- **Workflow:** change a server rule, run the parity test, update
  `storeProductRules.json` (and the named-rule copy, the mirrored message or
  the server-only list with its reason) until it passes. A rule the engine
  cannot check yet goes on the server-only list, never silently dropped.
- **Never stricter than the server.** The copy may be more lenient (the
  field comparisons only compare two numbers; a value the engine cannot
  judge passes), never refuse what the server accepts. A cross-field check
  the server lacks is added to the server first (as `min_qty`'s
  `lte:max_qty`, 2026-11-05), then copied.
- Rules apply only to fields with a control in the form; a server-required
  field with no control is left to the server.

## Required marks

Unchanged: the asterisk, `required` and the server rule still agree
([form-required-fields.md](form-required-fields.md)); `novalidate` only stops
the native bubbles, so the attributes stay for the marks, accessibility and the
required-fields ratchet.

## Adopting it in another form

1. Put the endpoint's rules in a JSON copy beside its form helpers and build
   a `ValidationSchema` from it (settings-gated and read-only fields as the
   server shapes them).
2. Copy any closure into `formValidationNamedRules.ts` and any missing message
   or attribute name into both locale files (from `lang/*/validation.php`).
3. Pass the schema to `ResourceFormPage`'s `validation` prop.
4. Add a parity test like the product one, and Vitest tests: an invalid
   value blocks the submit and shows the message, a valid form submits once,
   server errors show and clear when edited.
