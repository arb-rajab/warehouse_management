---
paths:
  - 'resources/js/pages/Store/**'
  - 'resources/js/components/Store*'
  - 'app/Http/Controllers/Store/Admin/**'
---

# A button that sends an admin to change one option must land on that option

When a button, banner link or alert exists to get an admin to change a specific option (a switch, a setting, a field), the page it opens must **navigate to that option and mark it**. The admin must not have to read a whole list of options to find the one the button was about. Linking to a page that merely contains the option is not enough, and neither is linking to a neighbouring page: the dashboard's "no payment method" alert once sent admins to Payment methods, which holds only the sandbox switches, while the Cash payment switch is on Features activation.

## How
- **Link to the page that holds the option**, passing the option's key as `?highlight=<key>`. Use Wayfinder: `activation({ query: { highlight: 'cash_payment' } })`. If the admin may not open that page, fall back to the nearest page they may open, as `Dashboard/Index.vue` does.
- **Validate the key on the server.** The controller passes `highlight` to the page only when it is one of that page's own keys (`SettingsController::page()` checks `PAGES[$page]['keys']`). An unknown or non-string value becomes `null`. Never echo the raw query value.
- **Mark it in the page.** `StoreBooleanField` takes `highlighted`: it adds `data-highlighted`, an amber ring, scrolls the option into view (centred) and focuses its control on mount. Pass `:highlighted="key === highlight"`. For a new kind of field, give it the same prop and behaviour rather than inventing another one.
- **Keep it working without script.** The option stays an ordinary, reachable form control; the highlight is added on top.

## Tests
- A component test that the highlighted option is marked, scrolled to and focused, and that an ordinary one is not.
- A page test that exactly one option is marked for a given `highlight`, and none without it.
- A controller test that a valid key is passed on and an unknown, array or missing one gives `null`.
- A test on the button's page that the link carries the `highlight` query.

## Where it applies today
`Dashboard/Index.vue` → Features activation (`cash_payment`). The SMTP alert opens a read-only page with no option to change, and "no product allows cash on delivery" opens the product list, where there is no single option to mark; add the highlight when such a target exists.
