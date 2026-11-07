---
paths:
  - 'resources/js/pages/Store/Admin/**/Index.vue'
  - 'resources/js/pages/Store/Admin/**/Show.vue'
  - 'resources/js/components/TableLink.vue'
  - 'resources/js/components/TableActionLink.vue'
  - 'resources/js/components/TableObjectLink.vue'
  - 'resources/js/components/StoreProductReport.vue'
  - 'app/Http/Resources/Store/**'
---

# A name that refers to another back office object is a gated link

Any column or cell (table, details list or summary) that shows an object which has its own back office page must render it as a link to that page. No plain text for a linkable object. This extends [pages.md](pages.md) (admin links) to the store back office; the full per-column audit is in [../store-list-links-audit-20261105.md](../store-list-links-audit-20261105.md).

## How
- Use `components/TableObjectLink.vue`: it renders `TableLink` (Inertia `Link` + trailing arrow) when `allowed` is true and the bare text otherwise. Do not hand-write `v-if` / `v-else` around `TableLink`, and do not use a native `<a>` for a back office page.
- `:href` is the Wayfinder action for the target (`edit as customerEdit` from `@/actions/App/Http/Controllers/Store/Admin/CustomerController`, an order's `show['/temp/admin/orders/{order}/show'](id)`), never a hard-coded URL. Pass the route object, not `.url` (js.md).
- `:allowed` is the same permission the target's own controller/list uses for that page: `access.can('edit_customer')` for the customer edit page, `edit_product_category` for a category, `edit_seller`, `edit_staff`, `edit_staff_role`, `edit_blog_category`, `edit_brand`, `manage_shipping_states`, `product_edit`, `view_order_details`. The link only hides a dead end; every route still checks its own permission (storeAccess.ts). Without access the name stays visible as plain text.
- A null reference (deleted account, typed-in name, no id) is plain text or its existing fallback, never a link to `undefined`.

## Destination: show page if there is one, otherwise the edit page
The store back office has no show page for most objects, so the edit page is the destination: customers and representatives (they share customer records: `customers.edit`), sellers (`sellers.edit`, keyed by the shop id, not the user id), products, categories, brands, staff, roles, states, blog categories. An order opens its show page. A seller's payment history, a trip's show page and similar existing links keep their own targets.

## The backend sends the id
A page cannot link what it only has a name for. When a resource shows an object's name it also sends that object's key, in the same nested object (`customer: { id, name }`) or as `<object>_id` beside a flat name (`category` + `category_id`). Add the id to the resource and the TypeScript type (`types/store.ts`, `storeReports.ts`, `storeRepresentatives.ts`), the test factory and the controller test's exact-payload assertion in the same change. Select the column in the query too.

## Not linkable
Objects with no page of their own stay text: countries (status switch only), shipping zones' names inside a city, the accountant of a representative payment (a user id, while `staffs.edit` is keyed by the staff id), wallet-report users (customer or seller, ambiguous), conversation senders and receivers (any account type), Dashboard top-product cards (the whole card already links to the storefront page), and a product name inside a form editor such as the campaign product rows (leaving would drop unsaved input). Record any new exception in the audit file.

## Tests
A page test asserts the link's href for a user who holds the permission and the plain text for one who does not. `TableObjectLink.test.ts` covers the component; a resource change updates the controller test that lists the payload.
