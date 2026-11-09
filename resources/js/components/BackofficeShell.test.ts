import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { t } from '@/lib/i18n';
import BackofficeShell from './BackofficeShell.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { usePage: usePageMock, Link: createLinkStub() };
});

let page: { url: string; props: Record<string, unknown>; flash: object };

function mountShell(props: Record<string, unknown> = {}) {
    return mount(BackofficeShell, {
        attachTo: document.body,
        props: {
            sidebarId: 'test-sidebar',
            area: 'Warehouse Admin',
            section: 'Rows',
            ...props,
        },
        slots: {
            nav: (slot: { close: () => void; collapsed: boolean }) =>
                h('nav', { 'aria-label': 'Menu' }, [
                    h(
                        'a',
                        {
                            href: '/admin/rows',
                            'data-collapsed': String(slot.collapsed),
                            onClick: (event: Event) => {
                                event.preventDefault();
                                slot.close();
                            },
                        },
                        'Rows',
                    ),
                ]),
            footer: '<p data-testid="footer">Footer</p>',
            actions: '<button type="button">Action</button>',
            default: '<p>Page content</p>',
        },
    });
}

function menuButton(wrapper: ReturnType<typeof mountShell>) {
    return wrapper.get(`button[aria-label="${t('nav.openMenu')}"]`);
}

function collapseButton(wrapper: ReturnType<typeof mountShell>) {
    return wrapper.get('[data-testid="sidebar-collapse"]');
}

beforeEach(() => {
    page = reactive({
        url: '/admin/rows',
        props: {
            brandName: 'Store name',
            auth: { user: { id: 7, name: 'Jane Doe', email: 'j@x.test' } },
        },
        flash: {},
    });
    usePageMock.mockReturnValue(page);
    window.localStorage.clear();
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('BackofficeShell', () => {
    it('shows the logo, the shared brand name and the area title in the sidebar', () => {
        const wrapper = mountShell();
        const aside = wrapper.get('aside#test-sidebar');

        expect(aside.get('img').attributes('src')).toBe(
            '/assets/img/33logoqr.png',
        );
        expect(aside.get('img').attributes('alt')).toBe('');
        expect(aside.get('[data-testid="sidebar-brand"]').text()).toBe(
            'Store name',
        );
        expect(aside.get('[data-testid="sidebar-area"]').text()).toBe(
            'Warehouse Admin',
        );
    });

    it('renders the nav and footer slots in the sidebar and the actions in the header', () => {
        const wrapper = mountShell();

        expect(wrapper.get('aside').find('nav').exists()).toBe(true);
        expect(
            wrapper.get('aside').find('[data-testid="footer"]').exists(),
        ).toBe(true);
        expect(wrapper.get('header').text()).toContain('Action');
    });

    it('names the area and the current section in the header crumb', () => {
        const wrapper = mountShell();
        const crumb = wrapper.get(`nav[aria-label="${t('nav.breadcrumb')}"]`);

        expect(crumb.text()).toContain('Warehouse Admin');
        expect(crumb.text()).toContain('Rows');
    });

    it('wraps the page in a padded max-w-screen-2xl column with the success banner', () => {
        page.flash = { success: 'Saved' };
        const wrapper = mountShell();
        const column = wrapper.get('main#main > div');

        expect(column.classes()).toEqual(
            expect.arrayContaining(['mx-auto', 'max-w-screen-2xl']),
        );
        expect(column.get('[role="status"]').text()).toBe('Saved');
        expect(column.text()).toContain('Page content');
    });

    it('hands the area to a full-bleed page without a column or banner', () => {
        page.flash = { success: 'Saved' };
        const wrapper = mountShell({ fullBleed: true });

        expect(wrapper.get('main').classes()).toEqual(
            expect.arrayContaining(['relative', 'min-h-0', 'flex-1']),
        );
        expect(wrapper.find('main .max-w-screen-2xl').exists()).toBe(false);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.get('main').text()).toContain('Page content');
    });

    describe('phone drawer', () => {
        it('opens from the header menu button and moves focus into the drawer', async () => {
            const wrapper = mountShell();
            const button = menuButton(wrapper);

            expect(button.attributes('aria-controls')).toBe('test-sidebar');
            expect(button.attributes('aria-expanded')).toBe('false');
            expect(wrapper.get('aside').classes()).toContain('hidden');

            await button.trigger('click');
            await nextTick();

            expect(button.attributes('aria-expanded')).toBe('true');
            expect(wrapper.get('aside').classes()).toContain('flex');
            expect(
                wrapper.find('[data-testid="sidebar-backdrop"]').exists(),
            ).toBe(true);
            expect(document.activeElement?.getAttribute('aria-label')).toBe(
                t('nav.closeMenu'),
            );
        });

        it('closes on Escape and returns focus to the menu button', async () => {
            const wrapper = mountShell();
            await menuButton(wrapper).trigger('click');
            await nextTick();

            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await nextTick();

            expect(menuButton(wrapper).attributes('aria-expanded')).toBe(
                'false',
            );
            expect(wrapper.get('aside').classes()).toContain('hidden');
            expect(document.activeElement).toBe(menuButton(wrapper).element);
        });

        it('ignores Escape once closed', async () => {
            const wrapper = mountShell();
            const other = document.createElement('button');
            document.body.append(other);
            other.focus();

            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await nextTick();

            expect(document.activeElement).toBe(other);
            expect(wrapper.get('aside').classes()).toContain('hidden');
        });

        it('closes from the backdrop and the close button, returning focus each time', async () => {
            const wrapper = mountShell();

            await menuButton(wrapper).trigger('click');
            await wrapper
                .get('[data-testid="sidebar-backdrop"]')
                .trigger('click');
            expect(menuButton(wrapper).attributes('aria-expanded')).toBe(
                'false',
            );
            expect(document.activeElement).toBe(menuButton(wrapper).element);

            await menuButton(wrapper).trigger('click');
            await wrapper
                .get(`button[aria-label="${t('nav.closeMenu')}"]`)
                .trigger('click');
            expect(menuButton(wrapper).attributes('aria-expanded')).toBe(
                'false',
            );
            expect(document.activeElement).toBe(menuButton(wrapper).element);
        });

        it('closes when a menu link is followed and after any visit', async () => {
            const wrapper = mountShell();

            await menuButton(wrapper).trigger('click');
            await wrapper.get('aside nav a').trigger('click');
            expect(menuButton(wrapper).attributes('aria-expanded')).toBe(
                'false',
            );

            await menuButton(wrapper).trigger('click');
            page.url = '/admin/users';
            await nextTick();
            expect(menuButton(wrapper).attributes('aria-expanded')).toBe(
                'false',
            );
        });
    });

    describe('icon rail', () => {
        it('starts open and folds from the header button, telling the slots', async () => {
            const wrapper = mountShell();
            const button = collapseButton(wrapper);

            expect(button.attributes('aria-controls')).toBe('test-sidebar');
            expect(button.attributes('aria-expanded')).toBe('true');
            expect(button.attributes('aria-label')).toBe(
                t('nav.collapseSidebar'),
            );
            expect(wrapper.get('aside').classes()).toContain('lg:w-64');

            await button.trigger('click');

            expect(button.attributes('aria-expanded')).toBe('false');
            expect(button.attributes('aria-label')).toBe(
                t('nav.expandSidebar'),
            );
            expect(wrapper.get('aside').classes()).toContain('lg:w-16');
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'true',
            );
            expect(
                wrapper.get('aside nav a').attributes('data-collapsed'),
            ).toBe('true');
            expect(
                wrapper.get('[data-testid="sidebar-brand"]').element
                    .parentElement?.className,
            ).toContain('lg:sr-only');
        });

        it('flips the rail icon in right-to-left', () => {
            const wrapper = mountShell();

            expect(collapseButton(wrapper).get('svg').classes()).toContain(
                'rtl:rotate-180',
            );
        });

        it('remembers the choice per user, sidebar and scope', async () => {
            let wrapper = mountShell({ collapseScope: 'map' });
            await collapseButton(wrapper).trigger('click');
            wrapper.unmount();

            expect(
                window.localStorage.getItem(
                    'backoffice.sidebar.test-sidebar.map.7',
                ),
            ).toBe('1');

            wrapper = mountShell({ collapseScope: 'map' });
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'true',
            );
            wrapper.unmount();

            wrapper = mountShell({ collapseScope: 'page' });
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'false',
            );
            wrapper.unmount();

            (page.props.auth as { user: { id: number } }).user.id = 8;
            wrapper = mountShell({ collapseScope: 'map' });
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'false',
            );
        });

        it('starts folded where the layout asks for it until the user unfolds it', async () => {
            const wrapper = mountShell({
                collapseScope: 'map',
                defaultCollapsed: true,
            });

            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'true',
            );

            await collapseButton(wrapper).trigger('click');
            expect(
                window.localStorage.getItem(
                    'backoffice.sidebar.test-sidebar.map.7',
                ),
            ).toBe('0');
        });

        it('still folds when storage is unavailable', async () => {
            vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
                throw new Error('blocked');
            });
            vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
                throw new Error('blocked');
            });
            const wrapper = mountShell({ defaultCollapsed: true });

            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'true',
            );
            await collapseButton(wrapper).trigger('click');
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'false',
            );
        });

        it('lets a layout unfold the rail through the nav slot', async () => {
            const Probe = defineComponent({
                setup() {
                    return () =>
                        h(
                            BackofficeShell,
                            {
                                sidebarId: 'probe',
                                area: 'Store Admin',
                                defaultCollapsed: true,
                            },
                            {
                                nav: (slot: { expand: () => void }) =>
                                    h(
                                        'button',
                                        {
                                            type: 'button',
                                            id: 'unfold',
                                            onClick: slot.expand,
                                        },
                                        'Group',
                                    ),
                            },
                        );
                },
            });
            const wrapper = mount(Probe, { attachTo: document.body });

            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'true',
            );
            await wrapper.get('#unfold').trigger('click');
            expect(wrapper.get('aside').attributes('data-collapsed')).toBe(
                'false',
            );
        });
    });
});
