import { expect, it } from 'vitest';

/**
 * The guard behind store-table-columns.md: every store back office table lets
 * users show and hide its optional columns, through `useTableColumns()` and
 * `<StoreColumnPicker>`. A page that renders a DataTable without them must be
 * listed here with the reason; a new table has no excuse but a missing
 * optional column.
 */
const withoutPicker: Record<string, string> = {
    'Blog/Categories/Index.vue':
        'only a name and the actions: no optional column to choose',
    'Staff/Roles/Index.vue':
        'only a name and the actions: no optional column to choose',
    'Product/AttributeValues/Index.vue':
        'only a value and the actions: no optional column to choose',
    'Sales/Orders/Show.vue':
        "one order's product lines: a document, not a list of records",
};

/** Components that render a DataTable inside a form, not as a list. */
const componentsWithoutPicker: Record<string, string> = {
    'StoreCampaignProductRows.vue':
        'the product rows of a campaign form (editable inputs)',
    'StoreOrderEntryFields.vue':
        'the product rows of an order form (editable inputs)',
};

const pageSources = import.meta.glob<string>('./**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});
const componentSources = import.meta.glob<string>(
    '../../../components/Store*.vue',
    { query: '?raw', import: 'default', eager: true },
);

const rendersTable = (source: string) => source.includes('<DataTable');
const usesPicker = (source: string) =>
    source.includes('useTableColumns(') &&
    source.includes('<StoreColumnPicker') &&
    source.includes(':columns="tableColumns"');

const pages = Object.entries(pageSources)
    .map(([path, source]) => [path.replace('./', ''), source] as const)
    .filter(([, source]) => rendersTable(source));
const components = Object.entries(componentSources)
    .map(
        ([path, source]) =>
            [path.replace('../../../components/', ''), source] as const,
    )
    .filter(([, source]) => rendersTable(source));

it('finds the store tables it guards', () => {
    expect(pages.length).toBeGreaterThan(40);
    expect(pages.map(([path]) => path)).toContain(
        'Customers/Customers/Index.vue',
    );
});

it('gives every store list page the column picker unless it is listed with a reason', () => {
    const missing = pages
        .filter(
            ([path, source]) => !usesPicker(source) && !(path in withoutPicker),
        )
        .map(([path]) => path);

    expect(missing).toEqual([]);
});

it('gives every shared store table component the column picker unless it is listed with a reason', () => {
    const missing = components
        .filter(
            ([path, source]) =>
                !usesPicker(source) && !(path in componentsWithoutPicker),
        )
        .map(([path]) => path);

    expect(missing).toEqual([]);
});

it('keeps the skip lists to pages that still render a table without the picker', () => {
    const stalePages = Object.keys(withoutPicker).filter((path) => {
        const entry = pages.find(([candidate]) => candidate === path);

        return entry === undefined || usesPicker(entry[1]);
    });
    const staleComponents = Object.keys(componentsWithoutPicker).filter(
        (path) => {
            const entry = components.find(([candidate]) => candidate === path);

            return entry === undefined || usesPicker(entry[1]);
        },
    );

    expect(stalePages).toEqual([]);
    expect(staleComponents).toEqual([]);
});

it('names every list with its own storage id', () => {
    const ids = [...pages, ...components].flatMap(([path, source]) =>
        [...source.matchAll(/listId: '([^']+)'/g)].map((match) => [
            match[1],
            path,
        ]),
    );

    expect(ids.length).toBeGreaterThan(40);
    expect(new Set(ids.map(([id]) => id)).size).toBe(ids.length);
    ids.forEach(([id]) =>
        expect(id).toMatch(/^[a-z][A-Za-z]*(\.[a-z][A-Za-z]*)+$/),
    );
});

it('tests the visibility of optional cells in every picker page', () => {
    // A picker page that defines optional columns tests their visibility in
    // its row template, so a hidden column cannot leave its cells behind.
    const unchecked = pages
        .filter(([, source]) => usesPicker(source))
        .filter(([, source]) => {
            const definitions = source.match(
                /useTableColumns\(\{([\s\S]*?)\n\}\);/,
            )?.[1];

            if (definitions === undefined) {
                return true;
            }

            const optional = [...definitions.matchAll(/key: '([^']+)'/g)]
                .map((match) => match[1])
                .filter(
                    (key) =>
                        !new RegExp(`key: '${key}'[^}]*required: true`).test(
                            definitions,
                        ),
                );

            return optional.length === 0
                ? false
                : !/\bshown\b/.test(source.slice(source.indexOf('<template')));
        })
        .map(([path]) => path);

    expect(unchecked).toEqual([]);
});
