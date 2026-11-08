# Visual identity proposal (temporary)

> This folder is a proposal for the owner to choose from. Nothing in the
> application has been changed. It will be deleted before the pull request is
> merged.

The owner asked for (a) a new visual identity for the public storefront and
(b) one shared look for the warehouse system (WMS, `AdminLayout.vue`) and the
store control panel (`StoreAdminLayout.vue`). There are no brand assets apart
from the store logo `public/assets/img/33logoqr.png`, a red "33" whose red
measures `#DA1B0C`. Both directions below start from that red.

Mock-ups are self-contained HTML files (inline CSS, the logo embedded, fonts
from Google Fonts for the preview only). Open one and use the language button
(or add `#ar` to the address) to switch to Arabic and right-to-left. The admin
mock-up follows the system light/dark setting.

## 1. Current state

Captured with Playwright Chromium (1440 x 900, device scale 2) against a
freshly seeded local copy (`migrate:fresh --seed` plus the e2e
`StoreCampaignBrowserSeeder`; faker product names, no product images, no home
page sliders or banners, which is why the home page looks empty).

| Screen | English | Arabic |
| --- | --- | --- |
| Storefront home | [current/storefront-home-en.png](current/storefront-home-en.png) | [current/storefront-home-ar.png](current/storefront-home-ar.png) |
| Storefront product listing (search) | [current/storefront-listing-en.png](current/storefront-listing-en.png) | [current/storefront-listing-ar.png](current/storefront-listing-ar.png) |
| Storefront product page | [current/storefront-product-en.png](current/storefront-product-en.png) | [current/storefront-product-ar.png](current/storefront-product-ar.png) |
| WMS dashboard | [current/wms-dashboard-en.png](current/wms-dashboard-en.png), dark: [current/wms-dashboard-en-dark.png](current/wms-dashboard-en-dark.png) | |
| WMS rows list | [current/wms-rows-en.png](current/wms-rows-en.png) | |
| WMS products list | [current/wms-products-en.png](current/wms-products-en.png) | |
| Control panel dashboard | [current/panel-dashboard-en.png](current/panel-dashboard-en.png) | |
| Control panel products list | [current/panel-products-en.png](current/panel-products-en.png) | |
| Control panel customers list | [current/panel-customers-en.png](current/panel-customers-en.png) | |

What the code shows today:

- **Storefront** (Blade, `resources/views/shop/frontend/layouts/app.blade.php`):
  the purchased "Active eCommerce" Bootstrap theme (`public/assets/css/vendors.css`,
  `aiz-core.css`, `bootstrap-rtl.min.css` for Arabic, an empty `custom-style.css`),
  Public Sans from Google Fonts for both languages (Arabic falls back to a
  system font), and an inline `:root` block. `--primary` comes from the
  `base_color` setting (default `#d43533`) and `aiz-core.css` reads
  `var(--primary)` 64 times, plus `--dark`, `--light`, `--success`,
  `--warning`, `--secondary`, `--soft-*`. Light mode only.
- **WMS**: top app bar, plain Tailwind (`gray-*`, `neutral-*` for dark,
  `blue-600` action buttons, `gray-900` primary buttons).
- **Control panel**: left sidebar, the same plain Tailwind palette, but a
  different shell, different button colours and spacing from the WMS.
- **Tokens**: `resources/css/app.css` has only `@import 'tailwindcss'` and a
  `--font-sans` of Instrument Sans. 170 files under `resources/js` use about
  1,440 hard-coded palette utilities (`bg-gray-50`, `text-blue-700`, ...).
  The warehouse map already has its own token file
  (`resources/css/warehouse-map/tokens.css`) with self-hosted Inter and Tajawal
  (`@fontsource/inter`, `@fontsource/tajawal`, already dependencies).
- **Dark mode**: exists for the WMS and the control panel and follows
  `prefers-color-scheme` only (no `@custom-variant dark`, no toggle; see
  `.ai/rules/views.md`). Both directions keep that and define dark tokens.
- **Fonts and CSP**: Inertia pages run under the strict policy in
  `config/secure-headers.php`, whose `font-src`/`style-src` allow only `self`
  and `fonts.bunny.net`. So the back office fonts must be self-hosted through
  Vite (`@fontsource/*`), as the warehouse map already does. Storefront Blade
  pages use `store.blade_csp` (`font-src https:`), so Google Fonts would work
  there, but self-hosting the same files keeps one source and no third-party
  request.

## 2. Direction A: "Souq" (warm, red-led)

The logo red becomes the brand colour everywhere, on a warm paper-and-ink
neutral scale, with saffron as a second accent for promotions. It reads as a
food market: warm, generous, confident. One typeface, IBM Plex Sans Arabic,
was designed with its Latin and Arabic sides together, so mixed lines
(product names with barcodes, Arabic with euro prices) keep one rhythm.

| Screen | English (LTR) | Arabic (RTL) | Dark |
| --- | --- | --- | --- |
| Storefront home | [direction-a/storefront-en.png](direction-a/storefront-en.png) | [direction-a/storefront-ar.png](direction-a/storefront-ar.png) | (storefront is light only) |
| WMS and control panel | [direction-a/admin-en.png](direction-a/admin-en.png) | [direction-a/admin-ar.png](direction-a/admin-ar.png) | [direction-a/admin-en-dark.png](direction-a/admin-en-dark.png) |

Mock-up files: [direction-a/storefront.html](direction-a/storefront.html), [direction-a/admin.html](direction-a/admin.html).

### Palette (light)

| Token | Hex | Use | Contrast (WCAG 2.x) |
| --- | --- | --- | --- |
| `brand` | `#C4170C` | primary buttons, active nav, links, prices | white on it 6.04:1 AA; on white 6.04:1, on page 5.66:1 |
| `brand-hover` / `brand-strong` | `#A11208` | hover, text on `brand-soft` | white 8.06:1; on `brand-soft` 7.05:1 |
| `brand-soft` | `#FDECEA` | selected rows, active sidebar item, icon wells | |
| `accent` | `#F2A900` | promotions, "New" tag, trader banner button | ink on it 8.58:1 (never white text) |
| `accent-strong` | `#8A5A00` | accent as text | 5.93:1 |
| `ink` | `#1F1A17` | body text | 17.24:1 |
| `muted` | `#6B625C` | secondary text | 5.95:1 on white, 5.58:1 on page |
| `line` / `line-strong` | `#ECE5DF` / `#D9D0C8` | borders, input borders | decorative |
| `page` / `surface` / `subtle` | `#FAF7F4` / `#FFFFFF` / `#F3EEE9` | backgrounds | |
| `success` (+ `-strong` on `-soft`) | `#1E7F4F` (`#17683F` on `#E9F6EF`) | | 5.00:1; badge 6.12:1 |
| `warning` (+ `-strong` on `-soft`) | `#B25E00` (`#7A4A00` on `#FEF3D7`) | | 4.67:1; badge 6.77:1 |
| `danger` (+ `-strong` on `-soft`) | `#B42318` (`#912018` on `#FEE4E2`) | | 6.57:1; badge 7.18:1 |
| `info` (+ `-strong` on `-soft`) | `#1D5FA8` (`#174C86` on `#E6EFFA`) | | 6.45:1; badge 7.49:1 |
| sidebar | `#FFFFFF`, text `#3D3530`, muted `#756C65`, active `#FDECEA` / `#A11208` | | muted 5.14:1 |

Dark (admin only): page `#14110F`, surface `#1E1A17`, subtle `#2A2522`, line
`#3A332E`, ink `#F5F0EB` (15.26:1), muted `#B5ACA4` (7.74:1), brand button
`#D42A1E` with white (5.06:1), brand text `#FF6B5E` (6.18:1), success
`#4ADE80` (9.92:1), warning `#FBBF24` (10.35:1), danger `#FF8A80` (7.57:1).

### Type, shape, depth

- **Font**: IBM Plex Sans Arabic 400/500/600/700 for Latin and Arabic, IBM
  Plex Mono 500/600 for barcodes, cell codes and dates. Self-hosted through
  `@fontsource/ibm-plex-sans-arabic` and `@fontsource/ibm-plex-mono` (new
  dependencies). Arabic body runs one step larger (15 px admin, 16 px store).
- **Radius**: sm 6, md 10, lg 14, xl 22, pill; buttons and inputs 10 px in
  the back office, pill buttons on the storefront.
- **Shadow**: card `0 1px 2px rgb(31 26 23 / .05), 0 1px 1px rgb(31 26 23 / .03)`,
  pop `0 16px 40px -12px rgb(31 26 23 / .28)`, primary button `0 1px 2px rgb(161 18 8 / .30)`.
- **Spacing**: Tailwind's 4 px scale, unchanged; page gutter 28 px, card
  padding 16-18 px, table cell 11 x 18 px.

Trade-off: the brand is red and so is danger. The mock-up keeps them apart
(danger is deeper `#B42318`, always with an icon or a dot badge), but a red
"Add pallet" button next to a red "Delete" needs care: destructive buttons use
the soft danger style, never solid.

## 3. Direction B: "Harbor" (calm, navy-led)

A deep harbour navy carries the interface; the logo red is kept as a sharp
accent for sales, the storefront hero call to action, notifications and the
active-item marker. It reads as a dependable logistics wholesaler and keeps
long working sessions in the WMS calm. The fonts are the ones the warehouse
map already self-hosts: Inter (Latin) and Tajawal (Arabic), so no new font
dependency.

| Screen | English (LTR) | Arabic (RTL) | Dark |
| --- | --- | --- | --- |
| Storefront home | [direction-b/storefront-en.png](direction-b/storefront-en.png) | [direction-b/storefront-ar.png](direction-b/storefront-ar.png) | (storefront is light only) |
| WMS and control panel | [direction-b/admin-en.png](direction-b/admin-en.png) | [direction-b/admin-ar.png](direction-b/admin-ar.png) | [direction-b/admin-en-dark.png](direction-b/admin-en-dark.png) |

Mock-up files: [direction-b/storefront.html](direction-b/storefront.html), [direction-b/admin.html](direction-b/admin.html).

### Palette (light)

| Token | Hex | Use | Contrast |
| --- | --- | --- | --- |
| `brand` | `#12355B` | primary buttons, nav bar, pagination | white on it 12.46:1 |
| `brand-hover` | `#0C2742` | hover | white 15.17:1 |
| `brand-soft` | `#E8EEF5` | selected rows, chips | `brand` on it 10.66:1 |
| `accent` | `#C4170C` | sale tags, hero call to action, notification dots, active marker | white on it 6.04:1 |
| `link` | `#1D4F86` | text links | 8.35:1 |
| `ink` | `#0F172A` | body text | 17.85:1 |
| `muted` | `#5B6577` | secondary text | 5.88:1 on white, 5.48:1 on page |
| `line` / `line-strong` | `#E2E8F0` / `#CBD5E1` | borders | decorative |
| `page` / `surface` / `subtle` | `#F5F7FA` / `#FFFFFF` / `#EEF2F6` | backgrounds | |
| `success` (badge `#166534` on `#DCFCE7`) | `#15803D` | | 5.02:1; badge 6.49:1 |
| `warning` (badge `#92400E` on `#FEF3C7`) | `#B45309` | | 5.02:1; badge 6.37:1 |
| `danger` (badge `#991B1B` on `#FEE2E2`) | `#B91C1C` | | 6.47:1; badge 6.80:1 |
| `info` (badge `#075985` on `#E0F2FE`) | `#0369A1` | | 5.93:1; badge 6.59:1 |
| sidebar | `#0E2440`, text `#C9D4E3` (10.42:1), muted `#8FA0B8` (5.86:1), active `#1D3F66` with white (10.74:1), marker `#E0301E` | dark in both modes | |

Dark (admin): page `#0B1220`, surface `#111A2B`, subtle `#1A2539`, line
`#263349`, ink `#E8EDF4` (14.80:1), muted `#9AA6B8` (7.06:1), brand button
`#2F6DB5` with white (5.28:1), brand text `#8DB8F0` (8.49:1), accent text
`#FF7A70` (6.85:1), success `#4ADE80` (9.99:1), warning `#FBBF24` (10.43:1),
danger `#F87171` (6.29:1).

### Type, shape, depth

- **Fonts**: Inter 400-800 for Latin, Tajawal 400/500/700/800 for Arabic
  (`:lang(ar)` puts Tajawal first), the system monospace for codes.
  Already installed: `@fontsource/inter`, `@fontsource/tajawal`.
- **Radius**: sm 4, md 6, lg 8, xl 12, pill; buttons and inputs 6 px.
- **Shadow**: card `0 1px 2px rgb(15 23 42 / .06)`, pop
  `0 10px 30px -10px rgb(15 23 42 / .30)`, flat buttons.
- **Spacing**: same 4 px scale and the same component paddings as A, a
  slightly denser storefront header (76 px).

Trade-off: the red logo sits on a navy chrome rather than leading it, so the
storefront looks less like today's red theme and more like a new brand; the
owner should be comfortable with navy being the colour customers remember.

## 4. Token names (both directions)

Defined once in `resources/css/app.css`. Raw values live in plain CSS custom
properties on `:root` (with a `@media (prefers-color-scheme: dark)` override,
matching the existing dark-mode rule), and `@theme inline` exposes them to
Tailwind so `bg-brand`, `text-muted`, `border-line`, `rounded-card` and so on
exist as utilities:

```css
@import 'tailwindcss';
@import './brand/fonts.css';   /* @fontsource imports, self-hosted by Vite */
@import './brand/tokens.css';  /* :root { --brand-...: ... } + dark override */

@theme inline {
    --font-sans: var(--brand-font-sans);
    --font-display: var(--brand-font-display);
    --font-mono: var(--brand-font-mono);

    --color-brand: var(--brand-brand);
    --color-brand-hover: var(--brand-brand-hover);
    --color-brand-strong: var(--brand-brand-strong);
    --color-brand-soft: var(--brand-brand-soft);
    --color-on-brand: var(--brand-on-brand);
    --color-accent: var(--brand-accent);
    --color-accent-strong: var(--brand-accent-strong);
    --color-accent-soft: var(--brand-accent-soft);
    --color-on-accent: var(--brand-on-accent);
    --color-ink: var(--brand-ink);
    --color-muted: var(--brand-muted);
    --color-line: var(--brand-line);
    --color-line-strong: var(--brand-line-strong);
    --color-page: var(--brand-page);
    --color-surface: var(--brand-surface);
    --color-subtle: var(--brand-subtle);
    --color-link: var(--brand-link);
    --color-focus: var(--brand-focus);
    /* success, warning, danger, info: each with -strong, -soft */
    /* sidebar: --color-sidebar, -ink, -strong, -muted, -line, -hover, -active, -active-ink, -indicator */

    --radius-sm: ...; --radius-md: ...; --radius-lg: ...; --radius-xl: ...;
    --radius-control: ...;   /* buttons and inputs */
    --shadow-card: ...; --shadow-pop: ...; --shadow-btn: ...;
}
```

The full value set for each direction is the `:root` block at the top of the
matching `admin.html` (light and dark) and `storefront.html` (storefront-only
`--sf-*` values for the strip, category bar, hero, tiles and footer).

**How the legacy storefront consumes them.** The theme CSS already reads
`--primary`, `--hov-primary`, `--soft-primary`, `--dark`, `--light`,
`--secondary`, `--success`, `--warning`, `--danger`, `--info` and their
`--soft-*` forms. A new Vite entry, `resources/css/storefront-brand.css`
(tokens, self-hosted fonts and a small override sheet), is loaded after
`aiz-core.css` in the storefront layout and maps the theme variables onto the
tokens (`--primary: var(--brand-brand)`, `--dark: var(--brand-ink)`, ...),
then restyles only the shell: header, category bar, product cards, buttons,
form controls, footer. The `base_color` / `base_hov_color` settings stop
driving the colour (or are kept and documented as an override that the new
sheet ignores; the owner decides).

## 5. Implementation plan (after confirmation)

Order chosen so each step is reviewable and the app looks consistent after
each one.

1. **Tokens and fonts.** Add `resources/css/brand/{tokens,fonts}.css`, wire
   them into `resources/css/app.css` (`@theme inline`, `--font-sans`), add
   `@fontsource/ibm-plex-sans-arabic` + `ibm-plex-mono` (A only; B reuses the
   installed Inter and Tajawal). Point `resources/css/warehouse-map/tokens.css`
   at the shared tokens so the map follows the new brand. No CSP change.
2. **Remap the Tailwind palette** in `@theme` so the existing ~1,440 utilities
   inherit the new look without touching 170 files: `gray-*`/`neutral-*` to
   the warm (A) or slate (B) neutral scale, `blue-*` (today's action colour)
   to the brand scale. Then migrate the shared building blocks to semantic
   utilities: buttons, `DataTable.vue`, `FilterDialog.vue`, `StoreListFilters.vue`,
   `StoreColumnPicker.vue`, `DetailList`/`DetailItem`, `IconStat.vue`,
   badges/`StoreStatusSwitch.vue`, `SuccessBanner.vue`, dialogs, form fields
   (`ResourceFormPage.vue`, `StoreSelectField`, `StoreSearchSelect`), pagination.
3. **One shell, two layouts.** Extract shared shell pieces (sidebar frame,
   sidebar link, header bar, account block) into components used by both
   `AdminLayout.vue` and `StoreAdminLayout.vue`. The layouts stay separate
   (`store-backoffice-migration.md` 2.2 says not to merge them; menus,
   permissions and active-state logic stay where they are). The mock-ups put
   the WMS in a sidebar like the control panel. That changes the WMS from a top
   bar to a sidebar, which conflicts with `.ai/rules/layouts.md` (app-bar
   active underline, `AccountMenu.vue` in the bar) and specs such as
   `admin-nav-active-indicator.spec.ts` and `admin-logout-confirmation.spec.ts`.
   The rule and those specs would be updated in the same change, keeping
   their intent (active indicator plus `aria-current`, icons, logout confirmation).
   **This needs the owner's yes** (see open questions).
4. **Pages.** Sweep `resources/js/pages/Admin/**` and `resources/js/pages/Store/Admin/**`
   for hard-coded colours the remap does not cover (status colours, charts
   in `StoreDashboardChart.vue`, cell-state colours stay as they are: they
   are a functional legend governed by `cell-state-visuals.md`).
5. **Storefront.** Add the `storefront-brand.css` Vite entry, load it in
   `shop/frontend/layouts/app.blade.php` after `aiz-core.css`, replace the
   Public Sans link with the self-hosted fonts, restyle `inc/nav.blade.php`,
   `inc/footer.blade.php`, the product card partial(s), home sections,
   listing filters and product page, keeping every route, form, the language
   toggle (`storefront-locale.md`) and the SEO partials as they are.
6. **Login and auth pages** (WMS `Auth/Login.vue`, store Blade login) and
   emails/PDFs only if the owner wants them in this round.
7. **Verification.** Vitest for touched components, the Playwright dark-mode,
   RTL and contrast specs (`tests/e2e/support/contrast.ts`), new screenshots
   in English and Arabic, then the attested pre-push run.

### Risks for the legacy storefront CSS

- `aiz-core.css` (213 KB) and `vendors.css` (460 KB) set colours in many
  places without variables (hex greys, `bg-dark`, `.text-reset`), so the
  override sheet has to be specific and grow page by page; untouched pages
  (account, checkout, seller shop) may show old greys until swept.
- Arabic uses a separate `bootstrap-rtl.min.css`; every override must use
  logical properties or be checked in RTL.
- Fifty Blade views extend the storefront layout; the seller panel
  (`aiz-seller.css`) is a separate theme and is out of scope unless asked.
- The `base_color` setting is editable in Website settings today; once tokens
  drive the colour, changing it there would no longer do anything visible.
- Inline `style` attributes and admin-authored CMS HTML (sliders, banners,
  popups) carry their own colours and images that the owner may want redone.
- Bootstrap's `.btn`, `.form-control` and `.card` are shared with third-party
  widgets (intl-tel-input, slick, select pickers): restyle with care and test
  checkout, registration and address forms.
- A font change alters text widths; long Arabic labels in the header and
  category bar need a check at 1280 px and on mobile.

## 6. Recommendation

**Direction A, "Souq".** It grows straight out of the only brand asset (the
red "33"), so the logo looks designed-in rather than placed on top; it keeps
continuity for existing customers who know a red store; and one typeface for
both scripts gives the most even Arabic/English result on product names,
which mix both. Its main risk, red brand against red danger, is handled by
the soft destructive style and the darker danger red.

Choose **B** instead if the owner prefers the back office to feel calmer for
all-day warehouse work, or wants the storefront to look clearly new; it also
costs no new font dependency.

## 7. Questions for the owner

1. Direction A or B (or A's storefront with B's back office)?
2. May the WMS move from its top bar to the same left sidebar as the control
   panel? (Needed for "identical"; it changes the layout rule and two e2e specs.)
3. Should the `base_color` website setting keep overriding the storefront
   colour, or be retired?
4. Are the login pages, emails/PDFs and the seller panel in scope now?
5. Any tweaks: brand name in the header ("Albaraka Holland" is a placeholder
   from the WhatsApp/footer context), accent colour, corner roundness, dark
   sidebar or light sidebar.
