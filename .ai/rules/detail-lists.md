---
paths:
  - 'resources/js/pages/**/Show.vue'
  - 'resources/js/pages/Store/Admin/**'
  - 'resources/js/components/DetailList.vue'
  - 'resources/js/components/DetailList.test.ts'
  - 'resources/js/components/DetailItem.vue'
  - 'resources/js/components/DetailItem.test.ts'
  - 'resources/js/components/StoreOrderEntryFields.vue'
  - 'resources/js/components/StoreSeller*Dialog.vue'
---

# Label/value details use DetailList: the label always sits above its value

A show page, a summary or a dialog that lists labelled values (an order's tracking code, a seller's bank account, a trip's driver) renders them with `components/DetailList.vue` and `components/DetailItem.vue`. Never a bare `<dl>`/`<dt>`/`<dd>`, never a two-column `grid-cols-2` of labels and values, and never a line with the label at one edge and the value at the far edge. The flat two-column `dl` on the order page was the case that prompted this: no grouping, no hierarchy, blank cells for empty values, numbers that did not line up. A label beside or far from its value lets the eye slip onto the next row, so the label is a small muted line directly above its value and the two are read together.

## How
- `DetailList` is a titled card (`title`) holding a `<dl>` of `DetailItem` children. `columns` (1 to 4) is the column count from `md` up (4 is two from `sm`, four from `lg`); a phone is always one column unless `keepColumns` is set, which is for short values such as amounts. `plain` drops the card, for a dialog, a form or a page that already has its own container. Pass layout classes (`md:col-span-2`, `mb-5`) to the list; they land on its section.
- `DetailItem` takes `label` and either `value` (a string or number) or the default slot for rich content. `full` spans every column, `preserve` keeps line breaks (notes, addresses), `amount` styles a money amount or percentage (larger, medium weight, tabular figures, bidi-isolated so `5%` never flips in right-to-left).
- Group related fields into titled cards, as the order page does: Order, People, Money, Notes and address. Money amounts are a stacked grid (`columns=2`, `keepColumns`), each under its own label, not a receipt list. Put the group titles in both `resources/js/locales/en.json` and `ar.json` ([lang.md](lang.md)).
- An empty value (null, undefined, blank text, or a slot that renders nothing, such as a `v-if` that is false) shows a muted "-" automatically. Do not write `?? '-'`, `|| 'N/A'` or leave a blank cell.
- A value that is another back office object stays a gated link: `TableObjectLink` (or `TableLink`) with the target's permission inside the slot, per [store-table-object-links.md](store-table-object-links.md). The object still links when it is shown through `DetailItem`.
- Logical alignment only: no `left`/`right`, `ml-`/`mr-`, `text-left`/`text-right`. The components already follow this; do not add physical classes around them.

## Not covered
KPI tiles with a large number (`DashboardStatTile`, the dashboard `dl`), data tables (`DataTable`), form fields, and the protected Blade templates (`resources/views/**`, including invoices), which keep their own markup.

## Tests
A page that adopts the component asserts the label/value pairs through `dl > div` / `dt` / `dd`, including the "-" for an empty value. `DetailList.test.ts` and `DetailItem.test.ts` cover the component; keep them current when you change a prop or a class.
