---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Table cells referencing another admin page must link to it
If a table column's value comes from a related resource that has its own admin page (a user, a row, etc.), render it as a link to that page instead of plain text — don't just display the name/label. Use the shared `resources/js/components/TableLink.vue` component (Inertia `Link` + trailing `@lucide/vue` ArrowUpRight icon) so linked cells are visually distinguishable from plain data. See `Admin/CellStatusLogs/Index.vue` for the pattern (links each cell in the "Cell" column to its row's show page via its `letter` route key). Only add the link when a target page actually exists for that resource — don't invent routes.

## Single-column Create/Edit forms use ResourceFormPage
The Head + AdminLayout + h1 + max-w-sm + Form + SubmitButton shell for a resource's Create/Edit page is `components/ResourceFormPage.vue` — do not re-write it per page. Props: title, action, submitLabel, submittingLabel. Put the resource's *FormFields component in the default scoped slot, which receives `{ errors }` (Form's `processing` is handled internally for the SubmitButton). See Rows/Create.vue, Rows/Edit.vue, Users/Create.vue, Users/Edit.vue.
