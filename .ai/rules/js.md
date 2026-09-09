---
paths:
  - 'resources/js/**'
---

# Js

## Build admin UI from the shared FormField / SubmitButton / DataTable components
Do not hand-write input, submit-button, or table markup in a page — the Tailwind class strings were duplicated 9x/5x/3x before being extracted.

- `components/FormField.vue` — label + input + error `<p>`. Props: id, label, type, value, error, inputClass. Extra attrs (min, maxlength, required, readonly, placeholder, autocomplete) pass through `$attrs` to the input.
- `components/SubmitButton.vue` — label / processingLabel / processing.
- `components/DataTable.vue` — wrapper + thead + tbody + empty state. Props: columns (`string | { label, sortKey }`, mix freely), rows (T extends {id:number}), emptyMessage, optional `sort: { by, direction }`; render cells via the `#row` slot. It derives the empty-state colspan from columns.length, so no hand-maintained colspan. A column with `sortKey` renders as a button and emits `sort` with that key on click — the header shows an up/down arrow when it's the active `sort.by`, otherwise a faded up-down arrow; the parent page owns applying the sort (see CellStatusLogs/Index.vue's `toggleSort()`), DataTable only renders state and emits intent.

FormField binds `:value.attr` (not `:value`) on purpose: the input stays uncontrolled so what the user typed survives the re-render after a failed submit. `tests/e2e/admin-form-retention.spec.ts` covers this — do not switch it to a plain `:value` binding.

Pagination.vue carries its own `mt-4`; do not wrap it in a spacing div.

## Shared table actions, slot labels, and delete confirmation
Alongside FormField / SubmitButton / DataTable, these are the single definitions for their concern — do not re-type the class strings or wording:

- `components/TableActionLink.vue` — pill link inside a table cell. `variant="primary"` (blue, default) or `"danger"` (red). Extra attrs (`method`, `as`, `:on-before`) fall through to the Inertia Link.
- `components/AddResourceLink.vue` — the "Add X" header button; bakes in the Plus icon. Props: href, label.
- `lib/confirm.ts` `confirmDelete(label)` — the only place the "Delete {label}? This cannot be undone." wording lives. Pass `` `row ${row.letter}` `` or a user's name.
- `lib/location.ts` `formatSlot(rowLetter, cellNumber, flatNumber)` — the `A3·2` slot label. The `·` separator must not be re-typed; CellStatusLogs/Index and Rows/Show had drifted copies of this format.

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

`<Link>` itself mounts fine unmocked when the test never triggers a visit; only stub it (via a `defineComponent`/`h` built *inside* the `vi.mock` factory — `vi.hoisted` can't close over other imports) when the component also needs `usePage`/`router` mocked, since mocking the module removes the real `Link` too.

`t()` from `@/lib/i18n` works unmocked in tests (documented in i18n.ts) — don't stub it.

## Every form field mirrors its backend rule client-side via HTML5 attributes
To keep invalid submissions from reaching the backend, every field bound to a FormRequest rule must carry the matching native HTML5 constraint, passed through FormField's `$attrs` (see js.md): `required` for `required`, `maxlength`/`minlength` for `max:`/`min:` on strings, `min`/`max`/`step="1"` for integer rules, `type="email"` for `email`. Only mirror rules that don't require a round trip (skip `unique`, `confirmed`-style cross-field checks stay client-side too when cheap — see UserFormFields.vue's `password`/`password_confirmation` `setCustomValidity` mismatch check). The backend rule stays authoritative; the frontend copy is just a flood guard. When adding/editing a form field, check its FormRequest's `rules()` and add the missing attribute in the same change, with a test asserting it (see UserFormFields.test.ts, RowFormFields.test.ts, Login.test.ts).

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
- Sort=ArrowUp/ArrowDown/ArrowUpDown, Close=X, Filter trigger=SlidersHorizontal, Clear=X, Apply/confirm=Check
- Cell states (mirrored by hand in the Flutter app, see js-components.md): empty=CircleDashed, full=Inbox, opened=PackageOpen; expiration=CalendarX, added-at/stored=CalendarPlus
- Warning/disabled hint=TriangleAlert, field/form error=CircleAlert, admin/privileged role=ShieldCheck, signed-in user=CircleUser, processing/loading=LoaderCircle (with animate-spin), ongoing/duration=Clock, transfer=ArrowLeftRight, empty table state=PackageSearch, brand mark=Warehouse
- Nav icons: LayoutDashboard, Rows3, Map, ArrowLeftRight, Users, LogOut, Languages

Before inventing an icon for a concept, grep the codebase for how that concept is already iconified (CellSlot.vue, AdminLayout.vue, DataTable.vue, TableActionLink usages) and reuse it. A directional/flow arrow (transfer, state-change) gets `rtl:rotate-180` and needs a Playwright e2e spec asserting the flip (see admin-cell-log-*-arrow-rtl.spec.ts) — a plain glyph icon (warning, error, role, loader) does not.

## Interactive elements need explicit cursor-pointer / disabled:cursor-not-allowed
Native `<button>` elements do not get a pointer cursor from the browser by default (only `<a>`/`<Link>` and checkbox/radio inputs do) — every clickable `<button>` must add `cursor-pointer` explicitly, and any `disabled:opacity-50` state on a button/input must be paired with `disabled:cursor-not-allowed` (see the existing pattern in UserFormFields.vue's admin-toggle checkbox). This was swept once (SubmitButton.vue, DataTable.vue's sort header, FilterMultiSelect.vue's trigger + option labels, FilterDialog.vue's close button, LanguageSwitcher.vue, lib/filters.ts's filterTriggerButtonClass/filterClearButtonClass/filterApplyButtonClass, FilterDateField.vue, FilterNumberField.vue, Cells/Index.vue, CellStatusLogs/Index.vue) — when adding a new `<button>` or a new disabled form control, add these classes at the same time instead of relying on the browser default.

## Run Prettier immediately after every Edit to a .ts/.vue file
After every Edit or Write to a `.ts`, `.vue`, or `.js` file, run Prettier on that file before any subsequent step:

```bash
npx prettier --write <path/to/file>
```

Do not defer this to a final gate — Prettier failures in CI cost a full round trip, and the diff is invisible until then. This applies to test files too (`.test.ts`).

## filterApplyButtonClass is the shared Apply-button class
lib/filters.ts's filterApplyButtonClass (bg-gray-900/white pill with a Check icon) is the single definition for a filter-dialog's submit/Apply button — shared by CellStatusLogs/Index.vue and Dashboard/Index.vue's custom-expiring-days dialog (extracted once the second call site appeared). Don't retype the class string. Also: filterTriggerButtonClass and FilterNumberField (previously shared only by CellHighlightFilters.vue/CellStatusLogs/Index.vue) now have a third user — Dashboard/Index.vue's custom-expiring-days field moved from a bare `@change`-wired number input into a FilterDialog + FilterNumberField + Apply-button flow, so a spinner click no longer fires a request that a subsequent click immediately cancels; see dashboard.md for the full rationale.
