---
paths:
  - 'resources/js/**'
  - 'resources/views/**'
  - 'app/Http/Requests/**'
  - 'app/Http/Controllers/**'
  - 'tests/**'
---

# Forms: required and optional fields

Every form tells the user which fields they must fill, and what it says
agrees with what the server enforces. Introduced on 2026-10-21; the history,
the first audit and the phased plan are in
[store-backoffice-migration.md §44](store-backoffice-migration.md#44-required-and-optional-form-fields-2026-10-21).

## The convention

**The server rule is the source of truth.** The mark, the HTML attribute
and the rule must agree. When they disagree, change the UI to match the
rule; change the rule only as a deliberate, tested behaviour change.

| Server rule (FormRequest or inline validator) | The control | Its label |
| --- | --- | --- |
| `required` or `accepted` (exact token), or `required_with` / `required_if` whose condition always holds in the form's normal state | native `required` (`aria-required="true"` where it is not a native input) | red asterisk, `aria-hidden`, plus visually hidden "required" |
| `required_if`, `required_with`, `required_without`, `Rule::requiredIf()` (conditional) | `:required` bound to the same condition, or none | the mark only while the condition is true in the rendered state; explain the condition in help text |
| `nullable`, `sometimes` without `required`, or no `required` | no `required` | nothing; see "(optional)" below |
| `confirmed` | `<field>_confirmation` is required exactly when `<field>` is | same as the field |
| `x.* => required` | each row's input (`tax[]` per tax) is required; a checkbox list or multi-select (`colors[]`) is not, since the item rule binds only what is sent | per row, or nothing |

- **Optional fields are unmarked.** The exception: in a form where most fields
  are required, label the few optional ones "(optional)" (`common.optional`,
  `<x-optional-mark />`), as the storefront registration form does. Never show
  both an asterisk and "(optional)" on one field. (The request that set this
  convention said "where most fields are optional"; "the few optional ones"
  only makes sense where most are required, so that is the rule.)
- **Every form with at least one required field shows the legend**
  "* Required fields" (`common.requiredLegend`, translated), once, at the top
  of the form.
- A checkbox posted with a hidden `0` fallback (`is_admin`) always sends a
  value: it is not marked and carries no `required`.
- The asterisk is decorative (`aria-hidden="true"`); screen readers hear the
  visually hidden "required" text inside the label (accessible name "Name
  required"). Its red is at least 4.5:1 in light and dark mode
  (`tests/e2e/store-required-mark.spec.ts` measures it).

## The components

**Vue (back office, auth pages)**

- Field components show the mark themselves when given `required`:
  `FormField` (now a prop, still forwarded as the native attribute),
  `StoreSelectField`, `StoreBooleanField` (required by default, as it always
  was; pass `:required="false"` where the rule is optional),
  `StoreUploadField`, `StoreRichTextField`, `ProductSelect`,
  `FilterNumberField`, `FilterDateField`, `StoreNewsletterRecipients`,
  `StoreOrderSearch`. Pass `required` only when the rule requires the field.
- A hand-written label for a raw `<input>`/`<select>`/`<textarea>`: put
  `<RequiredMark />` right after the label text, with no whitespace between
  (`>{{ label }}<RequiredMark /></label>`), so the accessible name is
  "Label required". Use `v-if` with the same condition as `:required`.
- The legend: `ResourceFormPage` renders `RequiredLegend` automatically when
  any `RequiredMark` inside its form is rendered (`lib/requiredFields.ts`,
  provide/inject; the mark registers itself during setup, so the legend
  appears in the same flush, with no layout shift). Opt out with
  `:required-legend="false"` only when the form prints its own. A form that
  is not a `ResourceFormPage` (a dialog, an inline `<Form>`) calls
  `provideRequiredFields()` and renders
  `<RequiredLegend v-if="hasRequiredFields" />` at its top.
- A new field component that takes `required` and renders a label must render
  `<RequiredMark v-if="required" />` and be added to the table in
  `components/RequiredFieldComponents.test.ts`; the guard in that file fails
  until it is.
- Spacing is logical (`ms-0.5`), so the mark follows the label in Arabic too.
- Playwright: a required field's accessible name ends in " required"
  (`getByRole('textbox', { name: 'Name required', exact: true })`), and
  `getByLabel` matches the label's text content, which is now `Name*required`.
  Locate required fields by role and accessible name; an exact `getByLabel`
  with the bare label no longer matches.

**Blade (storefront, account, seller panel)**

```blade
<x-required-legend />                                  {{-- once, at the top of the form --}}
<label>{{ translate('Full Name') }}<x-required-mark /></label>
<input type="text" name="name" required>
<label>{{ translate('Tax Number') }} <x-optional-mark /></label>
<input type="text" name="tax_number">
```

The components are in `resources/views/components/`; their strings go
through `translate()`. The mark's red is fixed (`#b91c1c`): the theme's
`.text-danger` is 3.6:1 on white. Associate labels with `for`/`id` where the
markup allows it, so the "required" text reaches the control's accessible
name.

## The audit: `php artisan forms:audit-required`

Development only (it refuses in production) and read-only. It writes
`.ai/required-fields-audit-<Ymd>.md` and `.json` (`--date=`, `--output=`).
It needs `node` and the installed `node_modules` (`@vue/compiler-sfc`,
`@babel/parser`) and runs every FormRequest's `rules()` on a scratch
in-memory SQLite database it migrates itself, so no `.env` database is
read. About 4 s.

- **Server side** (`App\Support\RequiredFields\ServerRules`): every FormRequest
  in `app/Http/Requests` (rules called with route models bound to unsaved
  instances and an unsaved user of the guard, in a rolled-back transaction)
  and every inline `$request->validate()` / `$this->validate()` /
  `Validator::make()` in a controller action or a `$this->helper()` it calls.
  Failures are listed as unparsed (class F), never skipped.
- **UI side**: Vue forms (`scan-vue-forms.mjs`) by the Wayfinder action an
  element's `:action` (or `v-bind="x.form()"`) resolves to through the file's
  imports, one form per ternary branch; controls are followed into child
  components with the props the parent passed. Blade forms under
  `resources/views/shop` by `route('...')` and HTTP verb.
- **Classes**, per form and field, with `file:line` on both sides:
  A server-required but no `required` (or no control at all), B
  server-required but no mark, C `required` or a mark while the rule is
  optional or absent, D a conditional rule with a static mark, E a control no
  rule names (dead field or unvalidated input), F unparsed. Severity: A and C
  high, B and D medium, E low, F info. Priority: P1 public storefront forms
  (registration, address, checkout, contact, seller registration), P2 other
  storefront, account and seller-panel forms, P3 back office.
- A radio or checkbox inside a `<fieldset>` whose own label has no mark takes
  the `<legend>` as its label: mark a radio group on its legend
  (`shipping_info.blade.php`).
- **What it cannot see**: controls inside a Blade component (the address
  forms' `<x-address-country-field>`: pin those with a Pest test), forms submitted from script (`useForm`,
  `router.post`), names built from runtime data (counted as unresolved
  controls), a `:required` that depends on runtime data ("dynamic": neither
  a gap nor a mismatch), legends (not checked), and Blade forms under
  `resources/views` outside `shop/`. Read a finding against the code before
  acting on it.

## The ratchet

`tests/Feature/RequiredFieldsRatchetTest.php` runs the audit over the store
back office (Vue pages under `resources/js/pages/Store` and every form
posting to a Store controller) and fails on a class A, B or C finding that is
not in `tests/Feature/Support/required-fields-baseline.json`. The baseline
held the 111 findings known on 2026-10-21 and can only shrink:

- fix a finding, and the second test fails until you delete its entry;
- a new form must not add an entry; add one only for a deliberate mismatch,
  with a `reason` saying why (the third test rejects an entry without one);
- the fingerprint is `class|form file|Controller@method|field`: renaming a
  page or a field shows as one fixed and one new finding; move the entry and
  keep its reason.

A fourth test covers the storefront and seller-panel Blade forms that have
been fixed (`FIXED_STOREFRONT_FORMS` in the same file, store-backoffice-migration.md
§45): any class A to D finding on one of them fails, with no baseline. Add a
form to that list in the PR that fixes it.

**Blade error display.** A storefront form that shares its page with another
form (an address modal on the profile page) validates into a named error bag
(`protected $errorBag = 'storeAddress'`) and reads `@error('field', 'storeAddress')`
and `old()` only when that bag has errors.

`components/RequiredFieldComponents.test.ts` is the Vitest side: every field
component renders the mark exactly when `required`, and every component that
forwards `required` to a labelled control is listed there.

## Before you finish a form

1. Read the endpoint's rules (`rules()` and any trait it uses, or the inline
   validator) and list required, conditional and optional fields.
2. Required: `required` on the field component, or native `required` plus
   `<RequiredMark />` in a hand-written label.
3. Conditional: bind `:required`/the mark to the same condition, and say the
   condition in help text.
4. Optional: no attribute; "(optional)" only if most fields are required.
5. The legend shows (automatic in `ResourceFormPage`; by hand elsewhere).
6. Test it: the marks and `required` attributes match the rule (Vitest for
   Vue, a rendered-page Pest test for Blade), and each required field left
   empty returns its own error (Pest).
7. Run `php artisan test --filter=RequiredFieldsRatchet` (store back office)
   and, for a storefront form, `php artisan forms:audit-required` and read its
   section for your form.
