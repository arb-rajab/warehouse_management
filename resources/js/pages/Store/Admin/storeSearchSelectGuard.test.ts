import { describe, expect, it } from 'vitest';

/**
 * The ratchet of .ai/rules/store-search-select.md: a select that searches
 * another table is a StoreSearchSelect. A store page or component may not run
 * its own remote search (a search box beside a native select, a result-button
 * list), list a whole entity table in a native select, or draw its own
 * combobox; the typeahead endpoints are only handed to StoreSearchSelect.
 */
const pages = import.meta.glob<string>('./**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});
const components = import.meta.glob<string>('../../../components/Store*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});
const actions = import.meta.glob<string>(
    '../../../actions/App/Http/Controllers/Store/Admin/*.ts',
    { query: '?raw', import: 'default', eager: true },
);

/** The component itself and its row are where the search lives. */
const OWNERS = [
    'components/StoreSearchSelect.vue',
    'components/StoreSearchOptionRow.vue',
];

const sources: Record<string, string> = Object.fromEntries([
    ...Object.entries(pages).map(([file, source]) => [
        `pages/Store/Admin/${file.replace('./', '')}`,
        source,
    ]),
    ...Object.entries(components).map(([file, source]) => [
        file.replace('../../../', ''),
        source,
    ]),
]);
const guarded = Object.entries(sources).filter(
    ([file]) => !OWNERS.includes(file),
);

/**
 * Controller (under Store/Admin) => its typeahead actions. A new typeahead
 * endpoint is added here, which makes the test demand its StoreSearchSelect.
 */
const TYPEAHEAD_ACTIONS: Record<string, string[]> = {
    OrderEntryController: ['search'],
    CampaignProductController: ['index', 'brands'],
    CouponController: ['productOptions'],
    NewsletterController: ['recipients'],
    ProductSearchController: ['search'],
    AddressController: ['states', 'cities'],
    TripController: ['orderOptions', 'customerOptions'],
    CustomerNotificationController: ['customers'],
    OrderConfigurationController: ['representatives'],
    ReportController: ['walletUsers', 'commissionSellers'],
    ProductController: ['sellerOptions'],
};

/** Native selects whose options are a whole bounded reference list, with the reason. */
const PRELOADED_LIST_EXCEPTIONS: Record<string, string> = {};

function importedActions(
    source: string,
): { controller: string; name: string }[] {
    const found: { controller: string; name: string }[] = [];

    for (const match of source.matchAll(
        /import\s*\{([^}]*)\}\s*from\s*'@\/actions\/App\/Http\/Controllers\/Store\/Admin\/([A-Za-z]+)'/g,
    )) {
        for (const specifier of match[1].split(',')) {
            const name = specifier
                .trim()
                .split(/\s+as\s+/)[0]
                .trim();

            if (name !== '') {
                found.push({ controller: match[2], name });
            }
        }
    }

    return found;
}

describe('store search select guard', () => {
    it('finds the store pages and components it guards', () => {
        expect(guarded.length).toBeGreaterThan(100);
        expect(Object.keys(sources)).toContain(
            'pages/Store/Admin/Sales/Orders/Create.vue',
        );
        expect(Object.keys(sources)).toContain(
            'components/StoreOrderEntryFields.vue',
        );
    });

    it('has no raw remote search: a search input in a file that fetches with useHttp', () => {
        const offenders = guarded
            .filter(
                ([, source]) =>
                    /type="search"/.test(source) && /useHttp/.test(source),
            )
            .map(([file]) => file);

        expect(
            offenders,
            'Use StoreSearchSelect for a select that searches another table (.ai/rules/store-search-select.md).',
        ).toEqual([]);
    });

    it('hands every typeahead endpoint to a StoreSearchSelect', () => {
        const offenders: string[] = [];

        for (const [file, source] of guarded) {
            for (const { controller, name } of importedActions(source)) {
                if (!TYPEAHEAD_ACTIONS[controller]?.includes(name)) {
                    continue;
                }

                // A report page passes its endpoint to StoreReportPage, which renders the select.
                const rendered =
                    /<StoreSearchSelect\b/.test(source) ||
                    /:selection-endpoint=/.test(source);

                if (!rendered) {
                    offenders.push(`${file} imports ${controller}.${name}`);
                }
            }
        }

        expect(offenders).toEqual([]);
        expect(sources['components/StoreReportPage.vue']).toContain(
            '<StoreSearchSelect',
        );
    });

    it('keeps the typeahead list current: every listed action exists', () => {
        for (const [controller, names] of Object.entries(TYPEAHEAD_ACTIONS)) {
            const source = Object.entries(actions).find(([file]) =>
                file.endsWith(`/${controller}.ts`),
            )?.[1];

            expect(source, `${controller} actions`).toBeDefined();

            for (const name of names) {
                expect(source).toMatch(new RegExp(`export const ${name}\\b`));
            }
        }
    });

    it('draws no combobox of its own', () => {
        const offenders = guarded
            .filter(([, source]) => /role="combobox"/.test(source))
            .map(([file]) => file);

        expect(offenders).toEqual([]);
    });

    it('does not list a whole customer, seller, user, order or city table in a native select', () => {
        const offenders = guarded
            .filter(
                ([file, source]) =>
                    !(file in PRELOADED_LIST_EXCEPTIONS) &&
                    /v-for="[A-Za-z]+ in (customers|representatives|sellers|users|orders|cities|states)"/.test(
                        source,
                    ),
            )
            .map(([file]) => file);

        expect(offenders).toEqual([]);
    });
});
