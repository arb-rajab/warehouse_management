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
- **Validate the key on the server.** The controller passes `highlight` to the page only when it is one of that page's own keys, through the `ReadsHighlight` concern (`SettingsController::page()` checks `PAGES[$page]['keys']` and `sections`). An unknown or non-string value becomes `null`. Never echo the raw query value.
- **Mark it in the page.** Wrap the field or section in `StoreHighlight` with `:active="highlight === '<key>'"`: it adds `data-highlighted`, an amber ring, scrolls the first control into view (centred) and focuses it on mount, or the block itself when it holds no control. `StoreBooleanField`'s `highlighted` prop uses it. Never invent another highlight mechanism.
- **Keep it working without script.** The option stays an ordinary, reachable form control; the highlight is added on top.

## Tests
- A component test that the highlighted option is marked, scrolled to and focused, and that an ordinary one is not.
- A page test that exactly one option is marked for a given `highlight`, and none without it.
- A controller test that a valid key is passed on and an unknown, array or missing one gives `null`.
- A test on the button's page that the link carries the `highlight` query.

## Where it applies today
The dashboard setup checklist (`StoreSetupChecklist.vue`) links every reason in `SetupChecklist::TARGETS` (.ai/rules/setup-checklist-reasons.md). The targets are:
- Features activation (`cash_payment`)
- Shipping configuration (`shipping_type`, `flat_rate_shipping_cost`, `shipping_cost_admin`, `cities`, `carriers`)
- the SMTP settings form's fields (`mail_mailer`, `mail_host`, and `mail_credentials`, the username and password pair) and the Firebase server status section (`firebase_server`)
- Google map (`google_map_api_key`), Currencies (`system_default_currency`) and General settings (`site_name`)
- Website header (`header_logo`) and footer (`contact_email`, `contact_phone`)
- Languages (`default_language`)

The product, category and tax lists hold no single option to mark, so their links carry no highlight.
