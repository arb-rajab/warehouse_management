---
paths:
  - 'app/Http/Controllers/Store/Admin/**'
  - 'app/Http/Controllers/Admin/**'
  - 'lang/en/store_admin.php'
  - 'lang/ar/store_admin.php'
  - 'lang/en/admin.php'
  - 'lang/ar/admin.php'
  - 'app/Http/Controllers/Concerns/FlashesAdminSuccess.php'
  - 'tests/Feature/Admin/**'
  - 'resources/js/components/SuccessBanner.vue'
  - 'resources/js/layouts/StoreAdminLayout.vue'
  - 'resources/js/layouts/AdminLayout.vue'
  - 'tests/Feature/Store/Admin/**'
---

# Every successful action that redirects tells the user it worked

Introduced on 2026-11-07. Users got no confirmation after an action succeeded, so
they refreshed the page to check. A success message now rides the redirect and the
layout shows it in the green `SuccessBanner` above the page content.

## The rule

- **Every user action that succeeds and redirects flashes a success message**:
  create, update, delete, bulk delete, status, featured and published toggles,
  duplicate, settings saves, approve, reject, ban, notes, password, send. Call it
  once the work has succeeded, just before the `return redirect...`.
- **Use the controller helper.** `use FlashesSuccess;`
  (`App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess`) and call
  `$this->flashSuccess('product_has_been_updated_successfully')`. It sends
  `Inertia::flash('success', __('store_admin.'.$key, $replace))`; pass `$replace`
  for `:placeholder` values. Do not write the `Inertia::flash` call by hand in a
  store controller, and do not use `->with('success', ...)` or the legacy
  `flash(...)` helper for it: `SuccessBanner` reads the Inertia flash only.
- **The text lives in `lang/en/store_admin.php` and `lang/ar/store_admin.php`**,
  the same key in both, in the same (alphabetical) order. Name keys like
  `<thing>_has_been_<created|updated|deleted>_successfully`, and name the thing
  ("Brand has been deleted successfully"; a toggle says "Brand status has been
  updated successfully"). Reuse an existing key when it already says it. The Arabic
  is written as natural Arabic, using the wording the same file already uses
  (`تمت إضافة X بنجاح`, `تم تحديث X بنجاح`, `تم حذف X بنجاح`).
- **Never flash on a failure.** A validation error, a thrown exception, an `abort`
  or a refused caller redirects (or fails) without a success message. Put the flash
  after the work, not before it, and not on an early return that changed nothing.
  When one method ends in two success redirects (`return_to` branches), flash
  before the branch.
- **Delegating actions flash once.** A route that only calls another action
  (`sellerPublished()` calling `updatePublished()`) leaves the flash to the action
  that does the work.
- **The only exceptions** are JSON endpoints (they return a body, not a redirect)
  and inline controls that already update the page visibly without a redirect.
  An action that changes nothing (a redirect that only opens a page) needs no
  message. Say why in a comment when a data-changing redirect is skipped.
- **New controllers, new actions and AI-generated code include the flash and an
  `assertInertiaFlash` test in the same change.** The success-path test of the
  action chains `->assertInertiaFlash('success', 'Brand has been created successfully')`
  (the English text) after its `assertRedirect(...)`. A new key without its Arabic
  pair fails `SellerLocalizationTest`.

## Where it shows

`SuccessBanner.vue` is mounted once, by the shared `BackofficeShell` that both
`StoreAdminLayout` and `AdminLayout` render through (layouts.md), not on the
full-bleed warehouse map. The map page renders it in its own bar above the map
(which takes no room without a message), for a pallet transfer that returns
there (`return_to=map`, warehouse-map.md). It reads `usePage().flash.success`, hides with its
X button, and shows again for the next flash even with the same wording. Do not add
a second banner, toast or alert to a page for the same message.

When a new flash arrives and the banner is not fully visible (dialog actions submit
with `preserveScroll`, so the page may sit scrolled below it), `SuccessBanner`
scrolls it into view with `scrollIntoView({ block: 'nearest', behavior: 'smooth' })`,
instantly under `prefers-reduced-motion`, and does nothing where `scrollIntoView` is
missing. Keep that inside the banner; pages do not scroll to it themselves.

## The WMS admin controllers

`app/Http/Controllers/Admin/**` follow the same rule with their own helper and strings:

- `use FlashesAdminSuccess;` (`App\Http\Controllers\Concerns\FlashesAdminSuccess`)
  and call `$this->flashAdminSuccess('row_has_been_deleted_successfully')`. It sends
  `Inertia::flash('success', __('admin.'.$key, $replace))`. Do not use the store
  `FlashesSuccess` trait or `store_admin` keys in a WMS controller.
- The text lives in `lang/en/admin.php` and `lang/ar/admin.php` (not `messages.php`,
  which holds errors), same keys, alphabetical, Arabic using the WMS vocabulary of
  `resources/js/locales/ar.json` (ممر row, منصة pallet, خلية cell, جولة التحقق
  verification round). Messages name the thing ("Row has been deleted successfully").
- Flash after the work and before the redirect, never on the failure branches
  (`withErrors`, the last-admin guard, a blocked row delete, a caught
  `InvalidSlotStateException`). `PalletController::handle()` takes the success key and
  flashes only after the action did not throw. A toggle names its outcome
  (cell activated or deactivated).
- Tests in `tests/Feature/Admin/**` chain `assertInertiaFlash('success', ...)` (the
  English text) on the success path of each action.
