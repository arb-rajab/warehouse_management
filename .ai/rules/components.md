---
paths:
  - 'resources/js/components/FilterDialog.vue,resources/js/components/FilterDialog.test.ts'
  - 'resources/js/components/FilterMultiSelect.vue,resources/js/components/FilterMultiSelect.test.ts'
---

# Components

## FilterDialog does not use Teleport
FilterDialog.vue renders its fixed-position overlay in place (no `<Teleport to="body">`), because AdminLayout has no ancestor with `overflow`/`transform` that would clip a `position: fixed` element, and Teleport would move the dialog's DOM out of the mounting component's subtree — `wrapper.get()`/`wrapper.find()` in @vue/test-utils can't see teleported content unless you query the real `document` instead, which would force every caller's test to switch from wrapper-scoped queries to `document.querySelector`. Don't add Teleport back without re-checking both constraints.

## FilterDialog moves focus in/out and traps Tab while open
FilterDialog.vue watches its `open` model: on open it stores `document.activeElement`, then (after nextTick) focuses the first focusable element inside the slot content (`contentRef`), falling back to the close button; on close it restores focus to whatever was focused before opening. Its existing document-level `keydown` listener also traps Tab/Shift+Tab between the dialog's first and last focusable elements (computed from the full dialog, header included) so keyboard focus can't leak to the page behind the overlay. Keep these refs (`dialogRef`, `closeButtonRef`, `contentRef`) if you restructure the template — the focus-trap query relies on `dialogRef`, and the "focus into content first" behavior relies on `contentRef` wrapping only the `<slot />`, not the header.

## FilterMultiSelect options are role=option with Arrow/Home/End navigation
Each option `<label>` carries `role="option"` + `:aria-selected="isChecked(...)"`, and its checkbox supports ArrowUp/ArrowDown (wrapping) and Home/End to move focus between options, via `setOptionRef(el, index)` populating `optionRefs` (a plain script function, not an inline template arrow-function ref callback — Vue's ref-unwrap-in-templates rules make an inline `(el) => optionRefs[index] = el` ambiguous/wrong; always define the setter as a real function in `<script setup>` and call it from the template). Tab-based navigation and click-to-toggle still work unchanged; arrow keys are an added enhancement, not a replacement.
