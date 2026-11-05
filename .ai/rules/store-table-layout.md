---
paths:
  - 'resources/js/components/DataTable.vue'
  - 'resources/js/components/DataTable.test.ts'
  - 'resources/js/components/IconStat.vue'
  - 'resources/js/components/IconStat.test.ts'
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/layouts/StoreAdminLayout.vue'
---

# Tables must not be wrapped in a way that grows their height

A list table shows as many rows as the viewport allows. A wide table scrolls sideways inside its wrapper; it never gets taller to fit the width.

## The contract lives in DataTable.vue
`components/DataTable.vue` is the only table wrapper for lists (store back office and warehouse alike). Do not hand-write a `<table>` or a second wrapper on a list page, and do not wrap `DataTable` in a card that sets `max-h-*`, `h-*`, `overflow-*` or a width that squeezes it.

- The `<table>` is `w-max min-w-full whitespace-nowrap`: as wide as its content, never narrower than the wrapper. `white-space` is inherited, so every `th`/`td` stays on one line. Do not add `table-fixed`, `w-full` or column `max-w-*` to squeeze columns; that is what made rows 4-7 lines tall.
- Rows are compact: `[&_td]:py-1.5` on the table (it overrides a page's `py-2`/`py-3`). The empty-state cell keeps its own `py-6!` and `whitespace-normal`.
- A cell that holds unbounded free text (message, note, description, comment, subject, title) opts out on the cell itself: `class="max-w-xs truncate"` plus `:title` with the full text. Do not use `whitespace-normal` or `whitespace-pre-wrap` on a list cell unless the text is short and bounded, because that is how a row grows tall again. A cell that deliberately stacks a few short lines (Info: sales/price/profit) is fine.
- Row actions (`TableActionLink` groups) use `flex flex-nowrap items-center gap-2`, not `flex-wrap`. Thumbnails in a list are `h-8 w-8`.

## Dense values use IconStat
A cell that stacks several short labelled numbers (sales, price, profit) shows them as one line of `components/IconStat.vue` pairs: a decorative icon, the value, and the full wording as an `sr-only` label plus a real tooltip (hover, keyboard focus, tap; Escape, a scroll or a click elsewhere closes it). The bubble is teleported to `body` and fixed-positioned, because the table scroller below would clip an absolute one. Reuse the existing label strings (`store.products.salesCount` and the like) so en and ar stay in step.

## One vertical scroller on the page, plus the table's own sticky-header scroller
The header must stay visible, and `position: sticky` sticks to the nearest scrolling ancestor. `overflow-x: auto` makes the wrapper a scroller in both axes (CSS turns `overflow-y: visible` into `auto`), so a header inside it can only stick if that wrapper is the vertical scroller too. `DataTable` therefore uses `data-table-scroller`: `max-h-[calc(100dvh-8rem)] overflow-auto`, with every `th` `sticky top-0 z-10` and its own background plus an inset bottom shadow (a border on a sticky cell would scroll away). That height keeps about 15-20 rows in view on a laptop; the page scrolls for everything below the table (pagination, selection controls).

- This is the one deliberate second scroller. Do not add another one around it (no `overflow-y-*`, `max-h-*` or `h-screen` on cards, panels or the layout `main`), and do not give the page's own list container a fixed height.
- The cost is that wheel scrolling over the table moves the table until it reaches its end, then chains to the page. Alternatives (a cloned header synced by JS, `overflow-x: clip`) either duplicate the column filter buttons or remove the horizontal scroll, so they are not used.
- No sticky first column: it is a checkbox or ID on most lists.
- The column-filter popover is a sibling outside the scroller (js-components.md); keep it there so it is not clipped.

## Page width
`StoreAdminLayout.vue` lets content grow to `max-w-screen-2xl` so more columns fit before scrolling. Keep it; do not cap the content narrower again for the sake of a list. The warehouse `AdminLayout` keeps its own `max-w-6xl`.

## Tests
`DataTable.test.ts` asserts `w-max`/`whitespace-nowrap`, the sticky `th` classes and the viewport-based `max-h`; `StoreAdminLayout.test.ts` asserts `max-w-screen-2xl`. Update them with any change here.
