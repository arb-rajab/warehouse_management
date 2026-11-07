---
paths:
  - 'app/Http/Controllers/Store/Admin/**'
  - 'app/Http/Controllers/Admin/**'
  - 'lang/en/store_admin.php'
  - 'lang/ar/store_admin.php'
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

`SuccessBanner.vue` is mounted once in `StoreAdminLayout` and in `AdminLayout`, not
on the full-bleed warehouse map. It reads `usePage().flash.success`, hides with its
X button, and shows again for the next flash even with the same wording. Do not add
a second banner, toast or alert to a page for the same message.
The WMS admin controllers (`app/Http/Controllers/Admin/**`) use the same flash and
the same `store_admin` keys; their helper and wiring are a separate change.
