---
paths:
  - 'app/Http/Controllers/Admin/RowController.php,resources/js/pages/Admin/Rows/Index.vue,resources/js/components/ActionErrorBanner.vue'
---

# Rows Js Components

## Surface non-field action errors with ActionErrorBanner, not a silent back()->withErrors()
When a controller action (e.g. `RowController::destroy`) rejects with `back()->withErrors(['key' => __('messages.xxx')])` for an error that isn't tied to a specific form field, the target index/listing page must read it via `usePage().props.errors.key` and render it with `components/ActionErrorBanner.vue` (props: `message?: string`; renders a dismissible-looking red alert with CircleAlert, or nothing). Before this, `RowController::destroy`'s "row has pallets" rejection had no frontend consumer at all — the delete button just silently did nothing on a race. Always route the message through `__('messages.*')` (lang.md), never a hardcoded string. Don't wire this into AdminLayout globally — that would double up with FormField's own inline error rendering on Create/Edit pages, which already receive the same `errors` bag through the `<Form>` slot.
