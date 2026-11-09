---
paths:
    - 'resources/js/**'
---

# Vue forms, shared UI, routing and frontend verification

## Build admin UI from the shared FormField / SubmitButton / DataTable components

Do not hand-write input, submit-button, or table markup in a page — the Tailwind class strings were duplicated 9x/5x/3x before being extracted.

- `components/FormField.vue` — label + input + error `<p>`. Props: id, label, type, value, error, inputClass. Extra attrs (min, maxlength, required, readonly, placeholder, autocomplete) pass through `$attrs` to the input.
- `components/SubmitButton.vue` — label / processingLabel / processing.
- `components/DataTable.vue` — wrapper + thead + tbody + empty state. Props: columns (`string | { label, sortKey }`, mix freely), rows (T extends {id:number}), emptyMessage, optional `sort: { by, direction }`; render cells via the `#row` slot. It derives the empty-state colspan from columns.length, so no hand-maintained colspan. A column with `sortKey` renders as a button and emits `sort` with that key on click — the header shows an up/down arrow when it's the active `sort.by`, otherwise a faded up-down arrow; the parent page owns applying the sort (see CellStatusLogs/Index.vue's `toggleSort()`), DataTable only renders state and emits intent.

FormField binds `:value.attr` (not `:value`) on purpose: the input stays uncontrolled so what the user typed survives the re-render after a failed submit. `tests/e2e/admin-form-retention.spec.ts` covers this — do not switch it to a plain `:value` binding.

Pagination.vue carries its own `mt-4`; do not wrap it in a spacing div.

## Shared table actions, slot labels, and delete confirmation

Alongside FormField / SubmitButton / DataTable, these are the single definitions for their concern — do not re-type the class strings or wording:

- `components/TableActionLink.vue` — pill link inside a table cell. `variant="primary"` (the outlined secondary pill, default) or `"danger"` (soft danger, `dangerPillVariantClass` in `lib/actionLink.ts`). Extra attrs (`method`, `as`, `:on-before`) fall through to the Inertia Link.
- Destructive buttons are soft danger, never a solid red next to the brand-red primary: `SubmitButton variant="danger"` (adds the Trash2 icon; every "Delete selection"/"Delete selected" submit) or `dangerButtonClass` from `lib/filters.ts` on a plain button.
- `components/AddResourceLink.vue` — the "Add X" header button; bakes in the Plus icon. Props: href, label.
- `lib/confirm.ts` `confirmDelete(label)` — the only place the "Delete {label}? This cannot be undone." wording lives. Pass `` `row ${row.letter}` `` or a user's name.
- `lib/location.ts` `formatSlot(rowLetter, cellNumber, flatNumber)` — the `A3·2` slot label. The `·` separator must not be re-typed; CellStatusLogs/Index and Rows/Show had drifted copies of this format.
- `components/StoreStatusSwitch.vue` — the on/off switch for a status flag toggled straight from a list row (role `switch`, locked state, RTL-aware). Never an Enable/Disable button pair; see [status-switches.md](status-switches.md).

## lib/date.ts formats dates/durations locale-aware, not with the browser default

`formatDate`/`formatDateTime` pass `currentLocaleTag()` (not `undefined`) to `toLocaleDateString`/`toLocaleString` — Arabic (`ar`) resolves to `ar-u-nu-latn` specifically so numbers render as Western digits (the plain `ar` tag's default numbering system is Arabic-Indic digits, which the rest of this app's UI doesn't use). Don't revert to `undefined`/the bare locale without re-checking that.

`formatDuration(seconds)` renders the two largest non-zero units ("2d 3h", "45m", "30s" — see date.test.ts), dropping to one unit once it's the smallest; unit suffixes come from `common.duration.*` in both locale files, not hardcoded, so a new locale needs those keys.

## Every component/lib file needs a co-located *.test.ts

Every `.vue` file in `components/` and `layouts/`, and every `.ts` file in `lib/`, must have a co-located `<Name>.test.ts` covering it (see the existing pairs, e.g. `DataTable.vue`/`DataTable.test.ts`). When adding a new file here or editing an existing one, add or update its test in the same change and run it (`npx vitest run <path>`) before finishing — don't rely on the file merely compiling/type-checking.

For components that call `usePage()` or `router.*` from `@inertiajs/vue3` (no Inertia app is booted in unit tests), mock the whole module:

```ts
const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
    router: { post: routerPostMock },
}));
```

`<Link>` itself mounts fine unmocked when the test never triggers a visit; only stub it (via a `defineComponent`/`h` built _inside_ the `vi.mock` factory — `vi.hoisted` can't close over other imports) when the component also needs `usePage`/`router` mocked, since mocking the module removes the real `Link` too.

`t()` from `@/lib/i18n` works unmocked in tests (documented in i18n.ts) — don't stub it.

## Every form field mirrors its backend rule client-side via HTML5 attributes

To keep invalid submissions from reaching the backend, every field bound to a FormRequest rule must carry the matching native HTML5 constraint, passed through FormField's `$attrs` (see js.md): `required` for `required`, `maxlength`/`minlength` for `max:`/`min:` on strings, `min`/`max`/`step="1"` for integer rules, `type="email"` for `email`. Only mirror rules that don't require a round trip (skip `unique`, `confirmed`-style cross-field checks stay client-side too when cheap — see UserFormFields.vue's `password`/`password_confirmation` `setCustomValidity` mismatch check). The backend rule stays authoritative; the frontend copy is just a flood guard. When adding/editing a form field, check its FormRequest's `rules()` and add the missing attribute in the same change, with a test asserting it (see UserFormFields.test.ts, RowFormFields.test.ts, Login.test.ts). A required field also shows the required mark and the form the legend: see form-required-fields.md.

A form that passes `ResourceFormPage` a `validation` schema (the store product forms, and every new form) validates in the browser through the shared layer instead ([client-side-validation.md](client-side-validation.md)): the form is `novalidate`, so the native attributes stay for the marks and accessibility while `lib/formValidation.ts` checks the rule copy (`lib/storeProductRules.json` for the products) and shows the server's wording inline. Changing a server rule there means: change the FormRequest, run its parity test (`ProductClientValidationParityTest`), update the rule copy until it passes.

## Add a Playwright e2e spec for browser-only behavior

tests/e2e (Playwright) exists only for behavior Vitest/jsdom cannot exercise — don't use it to re-test things unit/component tests already cover. Add or extend a spec (one concern per file, see existing tests/e2e/*.spec.ts for the pattern) whenever a change introduces:

- a native `<input type="date">` (or other native picker) styled with `dark:[color-scheme:dark]` — assert `toHaveCSS('color-scheme', 'dark')` (see admin-cell-log-date-filter-dark-mode.spec.ts)
- an icon/arrow flipped with `rtl:rotate-*` — assert the computed `rotate` CSS in both LTR and RTL (see admin-cell-log-*-arrow-rtl.spec.ts)
- a table cell linking to another admin page (TableLink) — assert the href and that clicking actually navigates (see admin-cell-log-table-links.spec.ts)
- a native browser dialog (`window.confirm`/`alert`, anything in lib/confirm.ts) — Playwright can drive `page.on('dialog', ...)`, jsdom cannot (see admin-delete-confirmation.spec.ts, which covers Rows/Index.vue and Users/Index.vue's `confirmDelete()` wiring)
- a real cross-request round trip that must retain form input after a server validation error (see admin-form-retention.spec.ts)
- new text/input color pairs in dark mode that could fail contrast (see login-dark-mode.spec.ts)

## FilterNumberField and filterTriggerButtonClass are the shared number-filter/trigger-button pieces

`components/FilterNumberField.vue` (mirrors `FilterDateField.vue`'s shape: props `id`, `label`, `placeholder?`, `disabled?`; `defineModel<string>()`) is the single definition for a labelled `type="number" min="1" step="1"` filter field — don't hand-roll this markup again (it was duplicated 4x across CellHighlightFilters.vue and CellStatusLogs/Index.vue before extraction). Note: because the input's `type="number"` is static, Vue auto-casts the emitted `update:modelValue` to a `number` even though the model/prop type is declared `string` — this matches the pre-existing app convention (e.g. `CellHighlightFiltersValue.expiresWithinDays: string` already held a runtime number the same way), so don't "fix" the type mismatch.

`lib/filters.ts`'s `filterTriggerButtonClass` is the single definition for the "open filter dialog" trigger button's class string (border/gray-700/hover), shared by `CellHighlightFilters.vue` and `CellStatusLogs/Index.vue` — alongside the already-documented `filterSectionHeadingClass`/`countBadgeClass`. Don't retype it.

## Always pair common UI affordances with an established @lucide/vue icon

When adding or touching a page/component, give common affordances an icon instead of leaving them as plain text/color — warnings, errors, loading/processing states, empty states, status/role badges, clear/apply actions, and duration/time tags all get one. Reuse the icon already established for that concept elsewhere rather than picking a new one:

- Add=Plus, Edit=Pencil, Delete=Trash2, View=Eye, external/related-record link=ArrowUpRight
- Sort=ArrowUp/ArrowDown/ArrowUpDown, Close=X, Filter trigger=SlidersHorizontal, Clear=X, Apply/confirm=Check, Column picker=Columns3
- Cell states (mirrored by hand in the Flutter app, see [cell-state visuals](cell-state-visuals.md)): empty=CircleDashed, full=Inbox, opened=PackageOpen; expiration=CalendarX, added-at/stored=CalendarPlus
- Warning/disabled hint=TriangleAlert, field/form error=CircleAlert, admin/privileged role=ShieldCheck, signed-in user=CircleUser, processing/loading=LoaderCircle (with animate-spin), ongoing/duration=Clock, transfer=ArrowLeftRight, empty table state=PackageSearch, brand mark=Warehouse
- Nav icons (AdminLayout's sidebar): LayoutDashboard, Building2 (warehouses), Rows3, Layers (pallets), Boxes (warehouse map), Package (products), ArrowLeftRight (cell log), ClipboardCheck (verification rounds), Users, Settings, CircleHelp (help), LogOut, Languages; sidebar rail toggle PanelLeftClose/PanelLeftOpen (with `rtl:rotate-180`)

Before inventing an icon for a concept, grep the codebase for how that concept is already iconified (CellSlot.vue, AdminLayout.vue, DataTable.vue, TableActionLink usages) and reuse it. A directional/flow arrow (transfer, state-change) gets `rtl:rotate-180` and needs a Playwright e2e spec asserting the flip (see admin-cell-log-*-arrow-rtl.spec.ts) — a plain glyph icon (warning, error, role, loader) does not.

## Interactive elements need explicit cursor-pointer / disabled:cursor-not-allowed

Native `<button>` elements do not get a pointer cursor from the browser by default (only `<a>`/`<Link>` and checkbox/radio inputs do) — every clickable `<button>` must add `cursor-pointer` explicitly, and any `disabled:opacity-50` state on a button/input must be paired with `disabled:cursor-not-allowed` (see the existing pattern in UserFormFields.vue's admin-toggle checkbox). This was swept once (SubmitButton.vue, DataTable.vue's sort header, FilterMultiSelect.vue's trigger + option labels, FilterDialog.vue's close button, LanguageSwitcher.vue, lib/filters.ts's filterTriggerButtonClass/filterClearButtonClass/filterApplyButtonClass, FilterDateField.vue, FilterNumberField.vue, CellStatusLogs/Index.vue) — when adding a new `<button>` or a new disabled form control, add these classes at the same time instead of relying on the browser default.

## Run Prettier immediately after every Edit to a .ts/.vue file

After every Edit or Write to a `.ts`, `.vue`, or `.js` file, run Prettier on that file before any subsequent step:

```bash
npx prettier --write <path/to/file>
```

Do not defer this to a final gate — Prettier failures in CI cost a full round trip, and the diff is invisible until then. This applies to test files too (`.test.ts`).

## An explicit ARIA role must not override the implicit role a test relies on

An explicit `role="X"` attribute replaces an element's implicit role entirely — it does not add to it. Putting `role="menuitem"` on a `<button>`/Inertia `Link as="button"` means `getByRole('button', ...)` (Playwright) or `wrapper.get('[role="button"]')` no longer matches it, even though it still looks and behaves like a button; the click target itself still works, so this fails silently until a test that queries by the old implicit role times out. This bit the old account menu's logout button: `role="menuitem"` was added without also implementing the ARIA menu pattern's required arrow-key roving-focus behavior, so `admin-logout-confirmation.spec.ts`'s `getByRole('button', { name: 'Log out' })` could never find it. The logout button in the sidebar footer (layouts.md) keeps its implicit `button` role for the same reason.

- Before adding `role="menu"`/`"menuitem"`/`"listbox"`/`"option"` etc. to an element, confirm you are implementing the full interaction pattern that role commits to (keyboard nav, focus management) — see `lib/useDismissibleListbox.ts`'s users (FilterMultiSelect.vue, FilterProductSelect.vue) for what a real one looks like. If you aren't, don't add the role; identify the element some other way (`id`, a data attribute, or its accessible name/label) instead.
- When adding any `role`/`aria-*` attribute to an element that existing tests already query (by role, label, or text), grep for that element's current selector across both `*.test.ts` and `tests/e2e/*.spec.ts` before pushing — not after CI fails.

## filterApplyButtonClass is the shared Apply-button class

lib/filters.ts's filterApplyButtonClass (the brand-red `bg-brand text-on-brand` button with a Check icon) is the single definition for a filter-dialog's submit/Apply button — shared by CellStatusLogs/Index.vue and Dashboard/Index.vue's custom-expiring-days dialog (extracted once the second call site appeared). Don't retype the class string. Also: filterTriggerButtonClass and FilterNumberField (previously shared only by CellHighlightFilters.vue/CellStatusLogs/Index.vue) now have a third user — Dashboard/Index.vue's custom-expiring-days field moved from a bare `@change`-wired number input into a FilterDialog + FilterNumberField + Apply-button flow, so a spinner click no longer fires a request that a subsequent click immediately cancels; see dashboard.md for the full rationale.

## Mutations use Inertia's <Form> or useForm — no router.post/put/delete plus a hand-rolled `processing` ref

A submit that mutates (ToggleCellActiveDialog, PalletActionsDialog, Products/Index.vue dialogs) goes through `<Form :action="route(model, { mergeQuery: {} })" :options="{ preserveScroll: true }" @success="...">`, with inputs given a `name` (hidden `<input name="return_to">` for non-visible fields). Use `useForm` + `form.transform(...).submit(routeObject, options)` only when the submit button sits outside the form (e.g. a FilterDialog `#footer`), since `<Form>`'s `processing` slot prop isn't reachable there. `processing` always comes from the form, never a local ref. A destructive non-form action (Products barcode removal) is a `<Link method="delete" as="button" :on-before="() => confirmDelete(label)">` using lib/confirm.ts, like Users/Rows. Don't re-add client-side "invalid count" messages: mirror the backend rule with native `required`/`min`/`step` attrs (FilterNumberField takes `name`/`required` for this).

## Read server errors from one of two places: the form's own `errors`, or usePageError(key)

Errors from a submitted `<Form>`/`useForm` are read from that form's `errors` (slot prop / `form.errors`); dialogs show `firstError(errors)` in an ActionErrorBanner. A banner for an action with no form (a delete/toggle link) uses `usePageError('action')` from lib/pageErrors.ts, which wraps `usePage().props.errors`. Never cast `usePage().props.errors` inline in a page, and never read errors from a router `onError` callback.

## Wayfinder URLs: route objects to Link/Form, model objects as params, aliased index imports

- Pass the route object, not `.url`, to `<Link :href>`, `<Form :action>` and components typed `string | UrlMethodPair` (TableLink, DashboardStatTile, TableActionLink, ResourceFormPage). `.url` is only for a native `<a>` download (QR/CSV exports) and `router.get`.
- One param style per route: the model object (`showUser(row.user)`, `toggleActive(cell)`, `destroyBarcode({ product, barcode })`); a bare id only when no object exists (`showCellVerificationRound(report.cell_verification_round_id)`, `productImage.url(item.pallet.product_id)`). Never `{ id: x.id }` or `{ cell: x.id }`.
- Always alias `index` (`index as rowsIndex`).
- Query strings use Wayfinder `{ query }` (`palletsIndex({ query: { state: 'empty' } })`), never `<Link :data method="get">` or a separate `query` prop. Booleans serialize as `1`, which the filter requests accept.

## Optional props are bare `?:`; withDefaults only for real defaults; shared Inertia test stubs

Declare optional props `foo?: T` and leave them out of `withDefaults` — a `foo: undefined` entry is noise (and `x: T | undefined` becomes `x?: T`). withDefaults is only for a non-undefined default (`tone: 'default'`, `disabled: false`). In tests, mock `@inertiajs/vue3` with testing/inertiaStubs.ts: `createLinkStub()`, `createFormStub(getSlotProps, onSubmit?)` (`onSubmit` receives the form's `FormData` on a native submit or the `submit` slot prop), `createUseFormStub(submitMock)`; assert child props (`getComponent(Form).props('action')`, `getComponent(Link).props('href')`) and emit `success` via `getComponent(Form).vm.$emit('success')`, not text alone.

## Shared dialog and filter labels live under `common`

- A filter dialog's title/apply/clear/close text is `common.filters.title|apply|clear|close`.
- An action dialog (toggle-active, pallet actions, the products page's box-count/minimum-pallets/barcode dialogs) closes with `common.close`.
- Don't add a per-page `x.filters.close`/`apply`/`clear`/`title` copy, and don't borrow another page's key (`cellLog.filters.close` used to label every dialog in the app, filters or not).
- Build a title with a parameterised key (`t('cellVerificationRound.show.title', { id })`), not by concatenating a translation with other text.

## One way to write the small things

- Import app code through the `@/` alias, siblings included (`@/components/FormField.vue`, `@/lib/i18n`), never `./`. Test files importing the component under test are the exception.
- Emit through `const emit = defineEmits<…>()` and call `emit()` in the template, not `$emit`.
- Templates use bare prop names, not `props.x`. Keep `const props =` only when the script reads it.
- Reuse the `lib/filters.ts` class constants (`plainFieldInputClass`, `filterTriggerButtonClass`, …) and `PageHeader.vue` rather than retyping their markup.

## Back-office colours come from the Direction A tokens

Write back-office markup with the semantic utilities from `resources/css/app.css`, which switch for dark mode by themselves, never a `gray-*`/`dark:neutral-*` pair. Every file under `components/`, `layouts/`, `lib/` and `pages/` is swept; `components/semanticColorsGuard.test.ts` fails on a raw palette class (`gray|neutral|slate|zinc|stone|blue|red|green|amber|orange|yellow|emerald|sky|...-NNN`, any variant prefix), a `bg|text|border|ring-white|black` utility, a stock `shadow-sm|md|lg|xl`, or a hex colour. Its allow-list is explicit and short: `lib/cellStateColor.ts` (the pinned cell-state legend, cell-state-visuals.md), `pages/Admin/Help/CellLogs.vue` (`'#123'` is a sample id) and the three components holding a native colour input's `#rrggbb` value/placeholder. Do not widen it; add a token instead.

| Meaning | Use |
| --- | --- |
| page, card, input, popover surface | `bg-page`, `bg-surface`; hover/quiet fill `bg-subtle`; strong fill `bg-line` |
| text | `text-ink` (body, labels, values), `text-muted` (hints, captions, icons, placeholders), `text-on-brand` on a brand fill |
| borders, dividers | `border-line` (cards, rows, `divide-line`), `border-line-strong` (inputs, buttons) |
| primary action, selected pill | `bg-brand text-on-brand shadow-btn hover:bg-brand-hover` (`selectedToggleClass`) |
| focus | `ring-focus-ring` / `border-focus` / `outline-focus`, `ring-offset-surface` |
| link in content | `text-link` (same as `TableLink`) |
| error, destructive | `text-danger` (message), `bg-danger-soft text-danger-strong` (badge, alert, button: `dangerButtonClass`), `border-danger/30` |
| success, warning | `bg-success-soft text-success-strong`, `bg-warning-soft text-warning-strong` (+ `border-success/30`, `border-warning/30`); solid `bg-success`/`bg-muted` only for a switch track |
| information | `bg-info-soft text-info-strong`, `text-info`, `border-info/30`, `ring-info` (a selected cell): badges, info alerts, selected-row tints, informational icons. Never `blue-*`, which renders brand red |
| tooltip | `bg-ink text-page shadow-pop` |
| modal backdrop | `bg-scrim` (`--scrim`, backoffice.css; darker in dark mode) |
| shadows, radius | `shadow-card` (cards, knobs), `shadow-pop` (dialogs, dropdowns, tooltips), `shadow-btn`; `rounded-md` controls, `rounded-lg` cards, dialogs and popovers (10/14 px tokens) |

Contrast: every text/background pair above is at least 4.5:1 in light and dark (ink/muted/link/danger on surface and page, each `*-strong` on its `*-soft`, `text-on-brand` on `bg-brand`; checked against the token values). A new token value must keep that. The info tokens are `--info-color/-strong/-soft` (tokens.css light; dark in backoffice.css).

Never write a hex colour in a class or style (`shadow-[inset_0_-1px_0_var(--line)]`, `var(--info-color)` in scoped CSS such as the map markers). Code that cannot read a CSS variable (Chart.js on a canvas, the Inertia progress bar) reads the token with `readCssToken('--brand')` from `lib/cssToken.ts` and re-renders on a `prefers-color-scheme` change. Native controls follow dark mode (`color-scheme: dark` in backoffice.css) and take `accent-color: var(--brand)`; leave both global. The saffron accent is reserved for the sidebar user avatar.

Codes (product barcodes and ids, cell slots, warehouse/order/trip/ticket/coupon/currency/country/language codes, SKUs) use `font-mono` (IBM Plex Mono) in tables, detail lists and dialogs. Destructive "Delete selection/selected" buttons are `dangerButtonClass` (or `SubmitButton variant="danger"`), never a red text link; inactive-cell and other status badges are soft danger, never a solid red. Page-level empty states, banners (`SuccessBanner`, `ActionErrorBanner`), pagination (bordered pills, the active one `selectedToggleClass`), tabs (`StoreLanguageTabs`), tooltips, popovers and dialogs share these tokens, so a WMS list and a control-panel list render the same.
