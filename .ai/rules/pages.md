---
paths:
  - 'resources/js/pages/**'
---

# Pages

## A value referencing another admin page must link to it, table or not
If a value displayed anywhere — a table column *or* a details/summary page's labelled field — comes from a related resource that has its own admin page (a user, a row, a round, etc.), render it as a link to that page instead of plain text — don't just display the name/label. Use the shared `resources/js/components/TableLink.vue` component (Inertia `Link` + trailing `@lucide/vue` ArrowUpRight icon) regardless of whether the surrounding layout is a table or a summary grid, so linked values stay visually distinguishable from plain data. See `Admin/CellStatusLogs/Index.vue` for the original table-cell pattern (links each cell in the "Cell" column to its row's show page via its `letter` route key) and `Admin/CellVerificationRounds/Index.vue`/`Show.vue` + `Admin/Users/Show.vue`'s reports table for both a table column and a non-table summary field doing the same for a worker/round.

Link to the specific page the rest of the app already uses for that resource in that context — don't assume the same target applies everywhere. A worker/user link defaults to their **show** page (`showUser({id})` from `Admin/UserController`) — see `CellVerificationRounds/Index.vue`/`Show.vue`, which link the round's worker there. The one pre-existing exception is `CellStatusLogs/Index.vue`'s "done by" column, which links to the user's **edit** page (`editUser({id})`) — that predates the show-page convention and hasn't been changed, so don't use it as the model for a new link; a new worker/user link elsewhere should go to show unless told otherwise. Only add the link when a target page actually exists for that resource — don't invent routes.

## Single-column Create/Edit forms use ResourceFormPage
The Head + AdminLayout + h1 + max-w-sm + Form + SubmitButton shell for a resource's Create/Edit page is `components/ResourceFormPage.vue` — do not re-write it per page. Props: title, action, submitLabel, submittingLabel. Put the resource's *FormFields component in the default scoped slot, which receives `{ errors }` (Form's `processing` is handled internally for the SubmitButton). See Rows/Create.vue, Rows/Edit.vue, Users/Create.vue, Users/Edit.vue.
