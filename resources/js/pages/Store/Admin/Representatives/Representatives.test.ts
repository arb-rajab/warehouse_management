import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    create as createPackage,
    destroy as destroyPackage,
    edit as editPackage,
    index as packagesIndex,
    store as storePackage,
    update as updatePackage,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import {
    accept,
    exportMethod,
    index as paymentsIndex,
    reject,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePaymentController';
import {
    bulkDestroy,
    destroy as destroyVisit,
    index as visitsIndex,
    show,
    show_all_visits_in_a_day,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import StoreRepresentativePackageFormFields from '@/components/StoreRepresentativePackageFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    representativePackage,
    representativePayment,
    representativeVisit,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import {
    expectColumnCanBeHidden,
    expectColumnLocked,
} from '@/testing/tableColumns';
import PackageCreate from './Packages/Create.vue';
import PackageEdit from './Packages/Edit.vue';
import PackagesIndex from './Packages/Index.vue';
import PaymentsIndex from './Payments/Index.vue';
import VisitsIndex from './Visits/Index.vue';

const {
    usePageMock,
    routerGetMock,
    routerPostMock,
    submitMock,
    formStateMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
    submitMock: vi.fn(),
    formStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formStateMock),
        useForm: createUseFormStub(submitMock, formStateMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

function setAccess(permissions: string[] = []) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/representatives',
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal(
        'confirm',
        vi.fn(() => true),
    );
    formStateMock.mockReturnValue({ errors: {}, processing: false });
    setAccess();
});

describe('representative package fields and create/edit screens', () => {
    it('renders every field label and mirrors validation constraints and field errors', () => {
        const pkg = representativePackage();
        const wrapper = mount(StoreRepresentativePackageFormFields, {
            props: {
                package: pkg,
                errors: { percentage: 'Invalid percentage' },
            },
        });
        expect(wrapper.findAll('label').map((label) => label.text())).toEqual([
            t('store.representativePackages.name') + '*' + t('common.required'),
            t('store.representativePackages.percentage') +
                '*' +
                t('common.required'),
        ]);
        expect(wrapper.get('#name').attributes()).toMatchObject({
            name: 'name',
            required: '',
            maxlength: '50',
            value: 'Gold',
        });
        expect(wrapper.get('#percentage').attributes()).toMatchObject({
            name: 'percentage',
            type: 'number',
            min: '0',
            max: '100',
            step: 'any',
            required: '',
            value: '12.5',
        });
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((field) => field.props('message')),
        ).toContain('Invalid percentage');
    });

    it('creates packages through the shared form page with labels layout action and errors', () => {
        formStateMock.mockReturnValue({
            errors: { name: 'Required' },
            processing: false,
        });
        const wrapper = mount(PackageCreate);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.representativePackages.create'),
            submitLabel: t('store.representativePackages.save'),
            submittingLabel: t('store.representativePackages.saving'),
            layout: StoreAdminLayout,
            cancelHref: packagesIndex(),
            action: storePackage(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            storePackage(),
        );
        expect(
            wrapper.getComponent(StoreRepresentativePackageFormFields).props(),
        ).toEqual({ errors: { name: 'Required' }, package: undefined });
        expect(wrapper.get('h1').text()).toBe(
            t('store.representativePackages.create'),
        );
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.representativePackages.save'),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .some((link) => link.text() === t('common.cancel')),
        ).toBe(true);
    });

    it('edits packages through the shared form page with the bound model and processing labels', () => {
        const pkg = representativePackage();
        formStateMock.mockReturnValue({ errors: {}, processing: true });
        const wrapper = mount(PackageEdit, { props: { package: pkg } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.representativePackages.edit'),
            submitLabel: t('store.representativePackages.save'),
            submittingLabel: t('store.representativePackages.saving'),
            layout: StoreAdminLayout,
            cancelHref: packagesIndex(),
            action: updatePackage(pkg),
        });
        expect(
            wrapper
                .getComponent(StoreRepresentativePackageFormFields)
                .props('package'),
        ).toEqual(pkg);
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            updatePackage(pkg),
        );
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.representativePackages.saving'),
        );
    });
});

describe('representative packages index', () => {
    it('renders its title labels rows actions pagination and native confirmation wiring', async () => {
        setAccess([
            'add_representative_package',
            'edit_representative_package',
            'delete_representative_package',
        ]);
        const pkg = representativePackage();
        const rows = paginated([pkg]);
        const wrapper = mount(PackagesIndex, {
            props: { packages: rows, filters: { per_page: 10 } },
        });
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.representativePackages.title'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('store.representativePackages.title'),
        );
        expect(
            wrapper.getComponent<ComponentPublicInstance>(DataTable).props(),
        ).toMatchObject({
            rows: [pkg],
            columns: ['Name', 'Discount percentage', 'Actions'],
            emptyMessage: t('store.representativePackages.empty'),
        });
        expect(wrapper.get('tbody').text()).toContain('Gold');
        expect(wrapper.get('tbody').text()).toContain('12.5%');
        expect(wrapper.getComponent(AddResourceLink).props()).toEqual({
            inertia: true,
            href: createPackage(),
            label: t('store.representativePackages.create'),
        });
        const actions = wrapper.findAllComponents(TableActionLink);
        expect(actions.map((action) => action.text())).toEqual([
            t('store.representativePackages.edit'),
            t('store.representativePackages.delete'),
        ]);
        expect(actions[0].props('href')).toEqual(editPackage(pkg));
        expect(actions[1].props()).toMatchObject({
            href: destroyPackage(pkg, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(actions[1].getComponent(Link).props('as')).toBe('button');
        expect(actions[1].getComponent(Link).attributes('method')).toBe(
            'delete',
        );
        const confirm = actions[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(confirm()).toBe(true);
        expect(window.confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Gold' }),
        );
        vi.mocked(window.confirm).mockReturnValue(false);
        expect(confirm()).toBe(false);
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: rows.meta.links,
            perPage: 10,
        });
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(routerGetMock).toHaveBeenCalledWith(
            packagesIndex().url,
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });

    it('hides every write button without permissions and renders the empty label', () => {
        const wrapper = mount(PackagesIndex, {
            props: { packages: paginated([]), filters: { per_page: 20 } },
        });
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findComponent(TableActionLink).exists()).toBe(false);
        expect(wrapper.text()).toContain(
            t('store.representativePackages.empty'),
        );
        const rowWrapper = mount(PackagesIndex, {
            props: {
                packages: paginated([representativePackage()]),
                filters: { per_page: 20 },
            },
        });
        expect(rowWrapper.findComponent(TableActionLink).exists()).toBe(false);
    });
});

function paymentPage(payments = [representativePayment()]) {
    return mount(PaymentsIndex, {
        props: {
            payments: paginated(payments),
            filters: {
                per_page: 20,
                search: '',
                accepted: false,
                rejected: false,
                pending: false,
            },
        },
    });
}

describe('representative payments index', () => {
    it('renders all labels escapes notes and wires atomic actions and PDF download', () => {
        setAccess([
            'approve_representative_payment',
            'export_representative_payment',
        ]);
        const payment = representativePayment({
            notes: '<script>bad()</script>',
        });
        const wrapper = paymentPage([payment]);
        expect(wrapper.get('h1').text()).toBe(
            t('store.representativePayments.title'),
        );
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.representativePayments.title'),
        );
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            'ID',
            'Customer',
            'Customer account ID',
            'Representative',
            'Receipt code',
            'Invoice code',
            'Amount',
            'Accountant',
            'Notes',
            'Status',
            'Actions',
        ]);
        expect(
            wrapper
                .get('form')
                .findAll('label')
                .map((label) => label.text()),
        ).toEqual(['Search payments', 'Pending', 'Accepted', 'Rejected']);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain('<script>bad()</script>');
        expect(
            wrapper
                .get('form#store-list-filters > button[type="submit"]')
                .text(),
        ).toBe(t('common.filters.apply'));
        const actions = wrapper.findAllComponents(TableActionLink);
        expect(actions.map((link) => link.text())).toEqual([
            'Accept',
            'Reject',
        ]);
        expect(actions[0].props('href')).toEqual(
            accept(payment, { mergeQuery: {} }),
        );
        expect(actions[0].getComponent(Link).attributes('method')).toBe('post');
        expect(actions[1].props('href')).toEqual(
            reject(payment, { mergeQuery: {} }),
        );
        expect(actions[1].getComponent(Link).attributes('method')).toBe('post');
        (actions[0].getComponent(Link).props('onBefore') as () => boolean)();
        expect(window.confirm).toHaveBeenCalledWith(
            t('store.representativePayments.confirm', {
                receipt: payment.receipt_code,
            }),
        );
        const download = wrapper
            .findAll('a')
            .find((link) => link.text() === 'Download PDF');
        expect(download?.attributes('href')).toBe(exportMethod.url(payment));
    });

    it('hides approvals and downloads for readers and approvals on decided receipts', () => {
        expect(paymentPage().findAllComponents(TableActionLink)).toHaveLength(
            0,
        );
        expect(
            paymentPage()
                .findAll('a')
                .some((link) => link.text() === 'Download PDF'),
        ).toBe(false);
        setAccess(['approve_representative_payment']);
        const wrapper = paymentPage([
            representativePayment({ status: 'accepted' }),
            representativePayment({ id: 'receipt-002', status: 'rejected' }),
        ]);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.get('tbody').text()).toContain('Accepted');
        expect(wrapper.get('tbody').text()).toContain('Rejected');
        expect(paymentPage([]).text()).toContain(
            'No representative payments found.',
        );
    });

    it('keeps search inline and the statuses in the dialog with a count badge', () => {
        const wrapper = mount(PaymentsIndex, {
            props: {
                payments: paginated([representativePayment()]),
                filters: {
                    per_page: 20,
                    search: 'MATCH',
                    accepted: true,
                    rejected: false,
                    pending: true,
                },
            },
        });
        const form = wrapper.getComponent(StoreListFilters);
        expect(form.props('action')).toEqual(paymentsIndex());
        expect(form.props('chips')).toEqual([
            {
                key: 'pending',
                label: t('store.representativePayments.status'),
                value: t('store.representativePayments.pending'),
                keys: ['pending'],
            },
            {
                key: 'accepted',
                label: t('store.representativePayments.status'),
                value: t('store.representativePayments.accepted'),
                keys: ['accepted'],
            },
        ]);
        const inline = wrapper.get('form#store-list-filters');
        expect(inline.get('input[name="per_page"]').attributes('value')).toBe(
            '20',
        );
        expect(inline.get<HTMLInputElement>('#search').element.value).toBe(
            'MATCH',
        );
        expect(inline.get('#search').attributes('maxlength')).toBe('255');
        const dialog = wrapper.get('[role="dialog"]');
        expect(
            dialog
                .findAll<HTMLInputElement>('input')
                .map((input) => [
                    input.attributes('name'),
                    input.attributes('value'),
                    input.element.checked,
                ]),
        ).toEqual([
            ['pending', '1', true],
            ['accepted', '1', true],
            ['rejected', '1', false],
        ]);
        expect(dialog.find('#search').exists()).toBe(false);
        expect(
            wrapper
                .get('form#store-list-filters > button[type="button"]')
                .text(),
        ).toContain('2');
    });

    it('keeps the applied filters when the page size changes', () => {
        const wrapper = mount(PaymentsIndex, {
            props: {
                payments: paginated([representativePayment()]),
                filters: {
                    per_page: 20,
                    search: 'MATCH',
                    accepted: true,
                    rejected: false,
                    pending: false,
                },
            },
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            paymentsIndex().url,
            {
                search: 'MATCH',
                accepted: true,
                rejected: false,
                pending: false,
                per_page: 10,
            },
            { preserveState: true, replace: true },
        );
    });
});

function visitPage(visits = [representativeVisit()]) {
    return mount(VisitsIndex, {
        props: {
            visits: paginated(visits),
            filters: { per_page: 20, search: '', date_from: '', date_to: '' },
        },
    });
}

describe('representative visits index', () => {
    it('renders all labels nullable data and keeps detail links as full loads', () => {
        const visit = representativeVisit({ note: '<script>bad()</script>' });
        const wrapper = visitPage([visit]);
        expect(wrapper.get('h1').text()).toBe('Representative visits');
        expect(wrapper.getComponent(Head).props('title')).toBe(
            'Representative visits',
        );
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            'Selection',
            'Representative',
            'Customer',
            'Note',
            'Visit date',
            'Has order',
            'Actions',
        ]);
        expect(
            wrapper
                .get('form')
                .findAll('label')
                .map((label) => label.text()),
        ).toEqual(['Representative or customer name', 'From date', 'To date']);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain('<script>bad()</script>');
        const details = wrapper
            .findAll('a')
            .find((link) => link.text() === 'Visit details');
        const day = wrapper
            .findAll('a')
            .find(
                (link) => link.text() === 'Representative visits on this day',
            );
        expect(details?.attributes('href')).toBe(show.url(visit));
        expect(
            wrapper.findAllComponents(Link).map((link) => link.props('href')),
        ).toContainEqual(show(visit));
        expect(
            wrapper.findAllComponents(Link).map((link) => link.props('href')),
        ).toContainEqual(
            show_all_visits_in_a_day(visit.representative!, {
                query: { date: '2026-10-08' },
            }),
        );
        expect(day?.attributes('href')).toBe(
            show_all_visits_in_a_day.url(visit.representative!, {
                query: { date: '2026-10-08' },
            }),
        );
        expect(wrapper.get('tbody').text()).toContain('No');
        const nullable = visitPage([
            representativeVisit({
                representative: null,
                customer: null,
                visit_date: null,
                has_order: true,
            }),
        ]);
        expect(nullable.get('tbody').text()).toContain('Yes');
        expect(
            nullable
                .findAll('a')
                .some(
                    (link) =>
                        link.text() === 'Representative visits on this day',
                ),
        ).toBe(false);
        expect(visitPage([]).text()).toContain(
            'No representative visits found.',
        );
    });

    it('gates deletions and confirms single and selected deletion with form-owned processing', async () => {
        expect(visitPage().find('input[type=checkbox]').exists()).toBe(false);
        setAccess(['delete_representative_visit']);
        const visit = representativeVisit();
        const wrapper = visitPage([visit]);
        const action = wrapper.getComponent(TableActionLink);
        expect(action.props('href')).toEqual(
            destroyVisit(visit, { mergeQuery: {} }),
        );
        expect(action.getComponent(Link).props('as')).toBe('button');
        expect(action.getComponent(Link).attributes('method')).toBe('delete');
        (action.getComponent(Link).props('onBefore') as () => boolean)();
        expect(window.confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'visit 4' }),
        );
        const bulkButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Delete selection')!;
        expect(bulkButton.attributes('disabled')).toBeDefined();
        await wrapper.get('input[type=checkbox]').setValue(true);
        expect(bulkButton.attributes('disabled')).toBeUndefined();
        expect(
            wrapper.get('input[type=checkbox]').attributes('aria-label'),
        ).toBe('Select visit 4');
        await wrapper.findAll('form')[1].trigger('submit');
        expect(submitMock).toHaveBeenCalledWith(
            bulkDestroy({ mergeQuery: {} }),
            expect.objectContaining({ id: [4] }),
            expect.objectContaining({
                preserveScroll: true,
                onSuccess: expect.any(Function),
            }),
        );
        expect(window.confirm).toHaveBeenLastCalledWith(
            t('common.confirmDelete', { label: 'selected visits' }),
        );
        submitMock.mockClear();
        vi.mocked(window.confirm).mockReturnValue(false);
        await wrapper.findAll('form')[1].trigger('submit');
        expect(submitMock).not.toHaveBeenCalled();
        formStateMock.mockReturnValue({
            errors: { id: 'Choose visits' },
            processing: true,
        });
        expect(visitPage().text()).toContain('Choose visits');
        expect(
            visitPage()
                .findAll('button')
                .find((button) => button.text() === 'Delete selection')
                ?.attributes('disabled'),
        ).toBeDefined();
    });

    it('keeps search inline and the dates in the dialog with a count badge', async () => {
        const wrapper = mount(VisitsIndex, {
            props: {
                visits: paginated([representativeVisit()]),
                filters: {
                    per_page: 20,
                    search: 'Rep',
                    date_from: '2026-10-01',
                    date_to: '',
                },
            },
        });
        const form = wrapper.getComponent(StoreListFilters);
        expect(form.props('action')).toEqual(visitsIndex());
        expect(form.props('chips')).toEqual([
            {
                key: 'date_from',
                label: t('store.representativeVisits.dateFrom'),
                value: '2026-10-01',
                keys: ['date_from'],
            },
        ]);
        const inline = wrapper.get('form#store-list-filters');
        expect(inline.get('input[name="per_page"]').attributes('value')).toBe(
            '20',
        );
        expect(inline.get<HTMLInputElement>('#search').element.value).toBe(
            'Rep',
        );
        const dialog = wrapper.get('[role="dialog"]');
        expect(
            dialog.findAll('input').map((input) => input.attributes('name')),
        ).toEqual(['date_from', 'date_to']);
        expect(dialog.find('#search').exists()).toBe(false);
        expect(
            wrapper
                .get('form#store-list-filters > button[type="button"]')
                .text(),
        ).toContain('1');
        // "to" cannot precede "from", also after the user edits "from".
        expect(wrapper.get('#date_to').attributes('min')).toBe('2026-10-01');
        await wrapper.get('#date_from').setValue('2026-10-05');
        expect(wrapper.get('#date_to').attributes('min')).toBe('2026-10-05');
    });

    it('keeps the applied filters when the page size changes', () => {
        const wrapper = mount(VisitsIndex, {
            props: {
                visits: paginated([representativeVisit()]),
                filters: {
                    per_page: 20,
                    search: 'Rep',
                    date_from: '2026-10-01',
                    date_to: '2026-10-08',
                },
            },
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            visitsIndex().url,
            {
                search: 'Rep',
                date_from: '2026-10-01',
                date_to: '2026-10-08',
                per_page: 10,
            },
            { preserveState: true, replace: true },
        );
    });

    describe('representative reference links', () => {
        it('links payment customer and representative to their edit pages only with edit_customer', () => {
            const row = representativePayment({
                customer_id: 31,
                representative: { id: 10, name: 'Rep One' },
            });
            const hrefs = (wrapper: ReturnType<typeof mount>) =>
                wrapper
                    .findAll('tbody a')
                    .map((link) => link.attributes('href'));

            setAccess(['edit_customer']);
            expect(hrefs(paymentPage([row]))).toEqual(
                expect.arrayContaining([
                    customerEdit(31).url,
                    customerEdit(10).url,
                ]),
            );

            const typedName = representativePayment({ customer_id: null });
            expect(hrefs(paymentPage([typedName]))).not.toContain(
                customerEdit(31).url,
            );

            setAccess([]);
            expect(hrefs(paymentPage([row]))).not.toContain(
                customerEdit(31).url,
            );
        });

        it('links visit customer and representative to their edit pages only with edit_customer', () => {
            const hrefs = (wrapper: ReturnType<typeof mount>) =>
                wrapper
                    .findAll('tbody a')
                    .map((link) => link.attributes('href'));

            setAccess(['edit_customer']);
            expect(hrefs(visitPage())).toEqual(
                expect.arrayContaining([
                    customerEdit(10).url,
                    customerEdit(11).url,
                ]),
            );

            setAccess([]);
            expect(hrefs(visitPage())).not.toContain(customerEdit(11).url);
        });
    });
});

it('the payments list lets staff hide an optional column and keeps the customer', async () => {
    window.localStorage.clear();
    const wrapper = paymentPage();
    await expectColumnCanBeHidden(wrapper, 'representatives.payments', {
        key: 'accountId',
        label: t('store.representativePayments.accountId'),
    });
    expectColumnLocked(wrapper, t('store.representativePayments.customer'));
    window.localStorage.clear();
});

it('the visits list lets staff hide an optional column and keeps the representative', async () => {
    window.localStorage.clear();
    const wrapper = visitPage();
    await expectColumnCanBeHidden(wrapper, 'representatives.visits', {
        key: 'note',
        label: t('store.representativeVisits.note'),
    });
    expectColumnLocked(wrapper, t('store.representativeVisits.representative'));
    window.localStorage.clear();
});
