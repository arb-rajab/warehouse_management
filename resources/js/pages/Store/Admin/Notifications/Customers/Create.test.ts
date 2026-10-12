import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as notificationsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerNotificationFormFields from '@/components/StoreCustomerNotificationFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { notificationCustomer, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({
            errors: { user_id: 'Choose a customer' },
            processing: false,
        })),
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});

beforeEach(() =>
    usePageMock.mockReturnValue({
        url: notificationsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    }),
);

describe('customer notification create', () => {
    it('passes the correct form action, labels, layout, recipients and validation errors', () => {
        const customers = [notificationCustomer()];
        const wrapper = mount(Create, { props: { customers } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.notifications.create.title'),
            action: store(),
            submitLabel: t('store.notifications.create.submit'),
            submittingLabel: t('store.notifications.create.submitting'),
            cancelHref: notificationsIndex(),
            layout: StoreAdminLayout,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper.getComponent(StoreCustomerNotificationFormFields).props(),
        ).toEqual({ customers, errors: { user_id: 'Choose a customer' } });

        for (const key of ['title', 'submit']) {
            expect(wrapper.text()).toContain(
                t(`store.notifications.create.${key}`),
            );
        }

        expect(wrapper.text()).toContain(t('common.cancel'));
    });
});
