---
paths:
  - 'resources/js/pages/**'
---

# Admin resource links and single-column form pages

## A value referencing another admin page must link to it, table or not
If a value displayed anywhere — a table column *or* a details/summary page's labelled field — comes from a related resource that has its own admin page (a user, a row, a round, etc.), render it as a link to that page instead of plain text — don't just display the name/label. Use the shared `resources/js/components/TableLink.vue` component (Inertia `Link` + trailing `@lucide/vue` ArrowUpRight icon) regardless of whether the surrounding layout is a table or a summary grid, so linked values stay visually distinguishable from plain data. See `Admin/CellStatusLogs/Index.vue` for the original table-cell pattern (links each cell in the "Cell" column to its row's show page via its `letter` route key) and `Admin/CellVerificationRounds/Index.vue`/`Show.vue` + `Admin/Users/Show.vue`'s reports table for both a table column and a non-table summary field doing the same for a worker/round.

A worker/user link goes to their **show** page (`showUser(user)` from `Admin/UserController`), not the edit page — see `CellStatusLogs/Index.vue`'s "done by" column and `CellVerificationRounds/Index.vue`/`Show.vue`'s worker link, all consistently pointed at show. Only add the link when a target page actually exists for that resource — don't invent routes.

## Single-column Create/Edit forms use ResourceFormPage
The Head + AdminLayout + h1 + max-w-sm + Form + SubmitButton shell for a resource's Create/Edit page is `components/ResourceFormPage.vue` — do not re-write it per page. Props: title, action, submitLabel, submittingLabel. Put the resource's *FormFields component in the default scoped slot, which receives `{ errors }` (Form's `processing` is handled internally for the SubmitButton). See Rows/Create.vue, Rows/Edit.vue, Users/Create.vue, Users/Edit.vue. Two optional props serve the store back office (store-backoffice-migration.md §3):
- `layout` (default `AdminLayout`): the store's pages pass `StoreAdminLayout`;
- `wide` lifts the `max-w-sm` limit for a form laid out as a grid, such as the role form's permission checkboxes.
