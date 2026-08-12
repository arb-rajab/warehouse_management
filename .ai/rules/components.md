---
paths:
  - 'resources/js/components/FilterDialog.vue,resources/js/components/FilterDialog.test.ts'
---

# Components

## FilterDialog does not use Teleport
FilterDialog.vue renders its fixed-position overlay in place (no `<Teleport to="body">`), because AdminLayout has no ancestor with `overflow`/`transform` that would clip a `position: fixed` element, and Teleport would move the dialog's DOM out of the mounting component's subtree — `wrapper.get()`/`wrapper.find()` in @vue/test-utils can't see teleported content unless you query the real `document` instead, which would force every caller's test to switch from wrapper-scoped queries to `document.querySelector`. Don't add Teleport back without re-checking both constraints.
