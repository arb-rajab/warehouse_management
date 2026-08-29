/**
 * The `locale`/`auth.user` shape every admin page test's `usePage()` mock
 * returns — pass overrides for a test that needs a different current user or
 * extra props (e.g. `errors`).
 */
export function defaultAuthProps(
    overrides: Record<string, unknown> = {},
): Record<string, unknown> {
    return {
        locale: 'en',
        auth: { user: { name: 'Jane Doe', id: 7 } },
        ...overrides,
    };
}

/**
 * Resets every mock in a `vi.hoisted()` group — the `beforeEach()`
 * boilerplate repeated across every admin page test.
 */
export function resetMocks(
    mocks: Record<string, { mockReset: () => void }>,
): void {
    Object.values(mocks).forEach((mock) => mock.mockReset());
}
