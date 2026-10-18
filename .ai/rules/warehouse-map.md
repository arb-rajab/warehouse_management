---
paths:
  - 'resources/js/warehouse-map/**'
  - 'resources/css/warehouse-map/**'
  - 'resources/js/pages/Admin/WarehouseMap/**'
  - 'app/Http/Controllers/Admin/WarehouseMapController.php'
  - 'tests/e2e/warehouse-map.spec.ts'
---

# Warehouse Map (2D + 3D)

The page at `/admin/warehouse-map` is a Vue/Inertia host (`pages/Admin/WarehouseMap/Index.vue`,
`<AdminLayout full-bleed>`) around a framework-free TypeScript module tree in
`resources/js/warehouse-map/`. The host only mounts it in `onMounted` and calls `dispose()` in
`onBeforeUnmount`; everything else is the module's own.

## Data

- The page reads `GET /admin/warehouse-map/rows` (session auth, `role:admin`), **not** the Sanctum
  `/api/v1/rows/full`. Both build on `Row::withFullGrid()` and `RowResource`, so they cannot drift.
- Both endpoints use `cache.headers:private;etag`. The client sends `If-None-Match` itself, so
  a 304 is visible to JS. The ETag saves transfer, not database work.
- A pallet's state is its **cell's** state; the payload has no `pallet.state`. Cells carry no
  `row_letter`; the parent row's `letter` is used. `product_boxes_count` is only present when the
  product's `setting` is eager-loaded (default 50 without a settings row).
- External product image URLs are blocked by the strict CSP (tile backgrounds only). Do not loosen the
  CSP for this page.

## Structure

- Layers depend downward only: `domain` (pure) ← `data`/`state` ← `plan2d`/`scene3d`/`ui` ← `main.ts`.
  Three.js is imported **only** inside `scene3d/`.
- State is one `Store` with actions and selectors; views receive a `Slice` and cannot see the whole store.
- Input adapters (keyboard + mouse, touch, gamepad) emit intents; camera modes consume intents and
  never read devices. `FrameLoop` is the single `requestAnimationFrame` owner.
- Every view has `mount()`/`dispose()`; listeners go through the view's `AbortController`. Never assign
  data to `innerHTML`; build nodes with `utils/dom`. Scene colours come from the CSS tokens
  (`css/warehouse-map/tokens.css`) via `Palette`.
- CSS classes are `wms-*` (BEM). Page state lives on `data-*` attributes of `.wms`. The base resets
  use `:where(.wms) button` so a single component class always wins; do not raise their specificity.
- Strings are in `resources/js/locales/{en,ar}.json` under `warehouseMap.*`; the language switch is the
  panel's locale POST followed by a reload.

## 2D performance

- Panning is compositor-only (a `transform` on a `will-change` layer); zooming re-rasters the whole
  `.wms-plan__world`. Profiled with a 640-column synthetic warehouse: the app's JS is negligible, raster is
  everything. Large blur radii and big gradients on that one scaled element dominate: an `inset` box-shadow
  with a 120px blur alone was ~60% of each zoom's raster. Keep the world's shadow small, draw vignettes as
  gradients, and keep tile styling cheap (tile shadows measured as free). The `is-coarse` level of detail
  drops shadows when zoomed out.
- Zooming also re-rasters every tile's shadows, gradients and product photos (photos roughly double the cost; with
  ~70% of columns full that is most of the page). `PlanView` therefore adds `is-zooming` to the world for the
  steps of a zoom plus 160ms, and `is-zooming` shares the `is-coarse` look (no shadows, bars, digits or photos).
  Add any new costly tile decoration to that shared selector list, not as an always-on style.
- Profile with Playwright's `trace`/`video` off (their DOM snapshots masquerade as page script time).

## Verification

- Unit tests are co-located (`*.test.ts`, jsdom). Browser behaviour (WebGL, pointer, phone layout) is
  covered by `tests/e2e/warehouse-map.spec.ts`; phone tests must use `.tap()`, because a mouse click flips
  the input kind to `mouse`.
- In the Claude sandbox `npm run build` fails on the font CDN: use `.claude/scripts/build_without_fonts.py`
  and `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium` for Playwright.

## Product picker filters by any of the checked products; `#products=` is its link
`SlotFilter.productIds` is ANDed with the search words, state chip and expiring chip, but ORed within itself (a slot holding any checked product matches). `ui/ProductPicker.ts` mirrors the admin's `FilterProductSelect.vue`: checked products pinned on top under "Deselect all", Arrow/Home/End between checkboxes, Escape/outside click closes, keys never reach the map's window-level shortcuts. It lists only products the warehouse holds (`listProductOptions`, memoized per warehouse by `createProductOptionsSelector`), with an Active/Inactive badge from the payload's `product_active`. `#products=12,34&state=full` (`filterHash`/`parseHash` in `domain/SlotCode.ts`) is read-only: it is applied on load and on `hashchange`, never written back, and the Pallets list's "view on map" button builds it. Keep the picker's options in one memoized array so the slice's shallow compare holds.

## Rows start from the right: aisle A-B and row A on the right, in 2D and 3D
Walking in from the entrance, the first aisle and row A are on the viewer's right (3D: -x, since the view looks along +z). `WarehouseGeometry.aisleCx`/`rowSide` own this; every builder, camera mode, picker and the minimap derive from them, so flip nothing else. The mirror is by position only: never scale a mesh or label by -1 (text would read backwards). The 2D plan keeps A-B and column 1 first in the DOM and places them with CSS: vertical uses `row-reverse` pairs with A in grid column 3; horizontal is a quarter turn of that (entrance and column 1 on the right, A at the top). `tests/e2e/warehouse-map.spec.ts` asserts both plan orientations.
