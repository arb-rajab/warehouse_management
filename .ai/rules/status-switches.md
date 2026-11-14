---
paths:
  - 'resources/js/components/StoreStatusSwitch.vue'
  - 'resources/js/components/StoreStatusSwitch.test.ts'
  - 'resources/js/pages/Store/Admin/**'
  - 'resources/js/pages/Admin/**'
  - 'tests/e2e/store-*.spec.ts'
---

# On/off status flags are StoreStatusSwitch switches

A status flag that an admin turns on or off straight from a list row (for sale,
active, featured, today's deal, approved, published, a reference record's
status, a language's RTL or activation) is a real switch:
`components/StoreStatusSwitch.vue`. Introduced on 2026-11-02, when the owner
found the old "Enabled + Disable X" button pairs (`StoreStatusButton`, deleted)
hard to read.

## Use it, not a button

- Never render such a flag as a SubmitButton with "Enable/Disable X" or
  "Publish/Unpublish" text, an `aria-pressed` button, a select plus Save, or a
  hand-built track. Use the component.
- Props: `action` (the Wayfinder route object), `checked`, `label` (the
  accessible name, a translated string such as the column's field label or a
  parameterised "X for {name}" key), optional `inputName` (`status` by
  default, `approved` or `value` where the endpoint names it so),
  `stateLabels` (`{ on, off }`, default `store.reference.enabled|disabled`),
  `locked` and `onBefore` (a confirmation before submitting). Extra hidden
  inputs such as `id` go in the default slot.
- It submits at once through Inertia's `<Form>` with `preserveScroll`, posting
  the opposite of `checked` in the hidden input; the server contract
  (field name, values `0`/`1`, routes) does not change. Errors show under it
  with `FieldError` (`firstError(errors)`).
- One size (h-6 w-11 track). Add another only when a page needs it.

## How it looks and behaves

- `<button role="switch" aria-checked>` with the label as `aria-label`, a
  colored track and sliding knob, and the state as visible text. On is green
  (`bg-success`, hover `bg-success-strong`) with a check in the knob; off is
  `bg-muted`: at least 3:1 against the page in both modes. The knob is
  `bg-surface shadow-card` and everything switches for dark mode through the
  tokens, so there are no `dark:` variants.
- Processing: disabled, `aria-busy`, a spinning `LoaderCircle` in the knob,
  "Saving…" as the text.
- Focus: `focus-visible:ring-2 ring-focus` with `ring-offset-surface`. Keyboard: Space and Enter (native button).
- RTL: the knob is placed with logical `start-*`, so it mirrors without an
  `rtl:` variant. Do not position it with physical `left`/`translate-x`.
- `locked` (`{ reason, hint? }`): no form; the switch stays focusable with
  `aria-disabled="true"`, a pale track and a lock in the knob; `reason` is
  shown under it and linked by `aria-describedby`; `hint` (or the reason) is
  the tooltip. Used for the default currency, the default language's
  activation, and Active on a products-list row whose `published` is
  Otajer-owned (`published_locked` from `ProductListResource`, see
  product-field-sources.md). The server keeps refusing such writes; the lock
  only explains it before the click.

## Permissions and read-only cells

Keep each page's own `access.can(...)` gate around the switch. Without the
permission the cell shows what it showed before (plain translated text, or
nothing on pages that showed nothing); do not render a disabled switch instead.

## Tests

- `StoreStatusSwitch.test.ts` covers the component. A page test asserts the
  switch's props (`action`, `checked`, `label`, `inputName`, `stateLabels`,
  `locked`) and that the permission-less cell renders its text.
- Browser tests find a switch by role and name and assert its state:
  `row.getByRole('switch', { name: 'Featured', exact: true })` with
  `toHaveAttribute('aria-checked', 'true')`. A `getByRole('button')` query no
  longer matches it (js.md: an explicit role replaces the implicit one).
  `tests/e2e/store-status-switch.spec.ts` covers keyboard toggling, the RTL
  mirror and dark-mode contrast.

## Not this component

Fields inside a larger form saved with one Save button stay form controls
(`StoreBooleanField` selects in settings, carrier, pickup point and Google Map
forms; the user form's `is_admin` checkbox). The warehouse `ToggleCellActiveDialog`
is a confirmation dialog with a reason, not a switch.
