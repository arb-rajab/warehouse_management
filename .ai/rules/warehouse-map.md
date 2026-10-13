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

## Verification

- Unit tests are co-located (`*.test.ts`, jsdom). Browser behaviour (WebGL, pointer, phone layout) is
  covered by `tests/e2e/warehouse-map.spec.ts`; phone tests must use `.tap()`, because a mouse click flips
  the input kind to `mouse`.
- In the Claude sandbox `npm run build` fails on the font CDN: use `.claude/scripts/build_without_fonts.py`
  and `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/opt/pw-browsers/chromium` for Playwright.
