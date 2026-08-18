---
paths:
  - resources/js/components/CellSlot.vue
  - resources/js/components/ResourceFormPage.vue
  - resources/js/components/UserFormFields.vue
---

# Js Components

## Cell-state styling is mirrored by hand in the Flutter mobile app
CellSlot.vue's stateClasses/stateBorderClasses/stateIcons maps are the canonical source for cell-state visuals: empty=gray-100/gray-400 border/CircleDashed, full=green-50/green-500 border/Inbox, opened=orange-50/orange-500 border/PackageOpen (light shades; dark variants alongside). The Flutter mobile app (separate repo, not in this codebase) has its own `_getBgColor`/`_getBorderColor`/`_getStateIcon` widget methods that duplicate these same three values as Dart `Color`/`IconData` literals — there's no shared code between the two, so whenever these Tailwind classes change, the Dart literals must be updated by hand to match (green=full, orange=opened, gray=empty; icons: inbox/package-open/circle-dashed equivalents). Don't extract this into a separate TS lib file — CellSlot.vue is still the only caller in this repo (duplication threshold not met on the TS side).

## ResourceFormPage supports an optional cancelHref back to the resource's index
ResourceFormPage.vue has an optional `cancelHref?: string | UrlMethodPair` prop; when given it renders a "Cancel" Link (t('common.cancel')) beside SubmitButton (wrapped together in `.flex.gap-3`, SubmitButton gets `class="flex-1"`). All four current Create/Edit pages (Rows, Users) pass their resource's `index()` action as `cancel-href`. Pass it on any new Create/Edit page using this component too, so users always have an in-app way back besides the browser back button.

## Password-confirmation mismatch shows both native validity and an app-styled FormField error
`syncConfirmationValidity()` still sets native `setCustomValidity` on `#password_confirmation` (js.md — don't remove, it's the flood guard before submit), but now also updates local `passwordValue`/`confirmationValue` refs feeding a `confirmationMismatchError` computed, bound to `password_confirmation`'s FormField `:error` prop. This makes the mismatch visible immediately with the same red-text+CircleAlert styling every other field error uses, instead of only a browser validation-bubble on submit. No server-side error exists for this key today, so this doesn't collide with backend `errors.password_confirmation`.
