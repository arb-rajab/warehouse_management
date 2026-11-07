# Store back office object links audit, 2026-11-05

Historical evidence (not current guidance); the standing rule is
[store-table-object-links](rules/store-table-object-links.md). Scope: every
`resources/js/pages/Store/Admin/**/*.vue` list and detail view, plus the Store*
table components (`StoreProductReport`, `StoreCampaignProductRows`), read on branch
`ccr-9db071d0-5jca06`. The warehouse admin (`pages/Admin`) is out of scope. Nothing
was run; this is a source reading plus the changes listed below.

## Linked in this change

Link = `TableObjectLink` (Wayfinder route, plain text without the permission). "id from"
says where the key comes from; "added" marks a backend payload change.

| Page / column | Object | Destination | Permission | id from |
|---|---|---|---|---|
| `Product/Products` / Category | category | `CategoryController::edit` (`?lang=`) | `edit_product_category` | `category_id` added to `ProductListResource` |
| `Staff/Staffs` / Role | staff role | `RoleController::edit` | `edit_staff_role` | `role.id` |
| `Sales/Orders` Index / Customer | customer | `CustomerController::edit` | `edit_customer` | `customer.id` |
| `Sales/Orders` Show / Customer, Representative | customer (representatives are customer records) | `CustomerController::edit` | `edit_customer` | `customer.id`, `representative.id` |
| `Sales/Orders` Show / Product (line items) | product | `ProductController::edit` | `product_edit` | `product_id` |
| `Notifications/Customers` / Customer | customer | `CustomerController::edit` | `edit_customer` | `customer.id` |
| `Sellers/Withdrawals` / Seller | seller | `SellerController::edit` (shop id) | `edit_seller` | `seller.shop_id` added to `SellerWithdrawalResource` |
| `Product/Reviews` / Customer | customer | `CustomerController::edit` | `edit_customer` | `customer.id` |
| `Setup/Cities` / State | state | `StateController::edit` | `manage_shipping_states` | `state.id` |
| `Setup/PickupPoints` / Manager | staff member | `StaffController::edit` | `edit_staff` | `staff.id` |
| `Blog/Posts` / Category | blog category | `BlogCategoryController::edit` | `edit_blog_category` | `category.id` |
| `Product/BrandDiscounts` / Name | brand | `BrandController::edit` | `edit_brand` | `id` |
| `Representatives/Payments` / Customer | customer | `CustomerController::edit` | `edit_customer` | `customer_id` added to `RepresentativePaymentResource` (null for a typed-in name: text) |
| `Representatives/Payments` / Representative | representative | `CustomerController::edit` | `edit_customer` | `representative.id` |
| `Representatives/Visits` / Representative, Customer | customer records | `CustomerController::edit` | `edit_customer` | `representative.id`, `customer.id` |
| `Trips/Trips` Show / Representative (checkpoints) | representative | `CustomerController::edit` | `edit_customer` | `representative.id` |
| `Trips/Orders` / Representative | representative | `CustomerController::edit` | `edit_customer` | `representative.id` |
| `Support/Tickets` Index, Show / Customer | customer | `CustomerController::edit` | `edit_customer` | `user.id` |
| `Support/ProductQueries` Index, Show / Customer, Product | customer, product | `CustomerController::edit`, `ProductController::edit` | `edit_customer`, `product_edit` | `user.id`, `product.id` |
| `Reports/SellerSales` / Seller | seller | `SellerController::edit` (shop id) | `edit_seller` | `id` (the shop) |
| `Reports/CommissionHistory` / Order code | order | order show page | `view_order_details` | `order_id` added to `CommissionReportResource` |
| `StoreProductReport` (Stock, In-house sales, Wishlist) / Product name | product | `ProductController::edit` | `product_edit` | `id` |

## Already linked before this change (kept)

`Categories` parent (`edit_product_category`), `Customers/Views`, `Trips/Orders`
customer, driver, truck and trip, `Trips/Trips` driver, truck and code, `Trips/Trucks`,
`Trips/Trips` Show driver, truck, customer and order, `Representatives/Visits` Show
customer and order, `Sellers/Payments` seller (payment history, `seller_payment_history`),
`Product/Reviews` product (storefront page), `Product/DigitalProducts` product (storefront),
`Customers/ClassifiedProducts` name (storefront), `Notifications` (personal) order and cart.

## Left as text, with the reason

| Where | Why |
|---|---|
| `Setup/States` / Country | countries have no page of their own (status switch only) |
| `Representatives/Payments` / Accountant | the payload carries a user id, `staffs.edit` is keyed by the staff id; no id to link without a lookup |
| `Reports/WalletHistory` / Customer | the wallet owner can be a customer or a seller, and the payload has only a name |
| `Support/Conversations` / Sender, Receiver | any account type (customer, seller, staff), only a name |
| `Dashboard` / top-product cards | the whole card links to the storefront product page; a nested back office link would be invalid markup |
| `Sellers/Commission` | a settings form, no table naming objects |
| `StoreCampaignProductRows` (flash deals, offers) | a form editor; leaving for the product page would drop unsaved input |
| `Customers/ClassifiedProducts` / Added by, `Customers/Packages` / Product upload | a label or upload id, not an object with a page |
| `Support/Tickets` Show reply authors | a reply author can be staff or a customer |

## Defaults chosen (open to change)

- Representatives link to the customer edit page: they share customer records
  (`routes/shop-admin.php`), and there is no separate representative page. The
  visits-of-a-day page (`rep-show`) stays reachable from the visits list's own action.
- A seller's name links to the seller edit page, not its payment history, except on the
  Payments page where the link was already the history.
