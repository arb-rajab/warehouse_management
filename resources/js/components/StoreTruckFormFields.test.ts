import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { photos as searchPhotos } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import { storeTruck, truckPhoto } from '@/testing/factories';
import StoreTruckFormFields from './StoreTruckFormFields.vue';

const { getMock, useHttpMock } = vi.hoisted(() => ({
    getMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({ useHttp: useHttpMock }));

function mountFields() {
    return mount(StoreTruckFormFields, {
        props: { truck: storeTruck(), photos: [truckPhoto()], errors: {} },
    });
}

describe('truck form fields', () => {
    beforeEach(() => {
        getMock.mockReset();
        useHttpMock.mockReset();
        useHttpMock.mockReturnValue(
            reactive({ get: getMock, processing: false }),
        );
    });

    it('renders labels, current values and backend native constraints', () => {
        const wrapper = mountFields();

        for (const [id, key] of [
            ['name', 'name'],
            ['model', 'model'],
            ['max_pallets_number', 'capacity'],
            ['license_plate', 'plate'],
        ]) {
            expect(wrapper.get(`label[for="${id}"]`).text()).toBe(
                t(`store.trucks.fields.${key}`),
            );
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('50');
        }

        expect(wrapper.get('#name').attributes('required')).toBeDefined();
        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'Cargo truck',
        );
        expect(wrapper.get('#model').attributes('required')).toBeUndefined();
        expect(wrapper.get('#description').attributes('maxlength')).toBe('255');
        expect(
            (wrapper.get('#description').element as HTMLTextAreaElement).value,
        ).toBe('Refrigerated');
        expect(wrapper.get('label[for="description"]').text()).toBe(
            t('store.trucks.fields.description'),
        );
        expect(wrapper.get('legend').text()).toBe(
            t('store.trucks.fields.photos'),
        );
        expect(wrapper.text()).toContain(t('store.trucks.fields.photosHint'));
        expect(wrapper.get('label[for="photo_search"]').text()).toBe(
            t('store.trucks.fields.photoSearch'),
        );
        expect(wrapper.get('#photo_search').attributes('maxlength')).toBe(
            '255',
        );
        expect(wrapper.get('button').text()).toBe(
            t('store.trucks.fields.searchPhotos'),
        );
        expect(wrapper.get('input[name="photos[]"]').attributes('value')).toBe(
            '7',
        );
        expect(wrapper.get('input[type="checkbox"]').element).toHaveProperty(
            'checked',
            true,
        );
    });

    it('keeps a dirty description when server defaults or errors rerender the form', async () => {
        const wrapper = mountFields();
        const description = wrapper.get('#description');
        await description.setValue('Keep this unsaved description');
        await wrapper.setProps({
            truck: storeTruck({ description: 'Server default changed' }),
            errors: { license_plate: 'Already taken' },
        });
        expect((description.element as HTMLTextAreaElement).defaultValue).toBe(
            'Server default changed',
        );
        expect((description.element as HTMLTextAreaElement).value).toBe(
            'Keep this unsaved description',
        );
        expect(wrapper.text()).toContain('Already taken');
    });

    it('searches, pages and preserves selected photos across result changes', async () => {
        const wrapper = mountFields();
        await wrapper.get('#photo_search').setValue('new');
        await wrapper.get('button').trigger('click');
        expect(getMock.mock.calls[0][0]).toBe(
            searchPhotos.url({ query: { search: 'new', page: 1 } }),
        );
        const options = getMock.mock.calls[0][1];
        options.onSuccess({
            data: [truckPhoto({ id: 8, name: 'New photo' })],
            has_more: true,
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('Truck photo');
        expect(wrapper.text()).toContain('New photo');
        await wrapper.get('input[type="checkbox"][value="8"]').setValue(true);
        expect(wrapper.findAll('input[name="photos[]"]')).toHaveLength(2);
        const more = wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text() === t('store.trucks.fields.morePhotos'),
            )!;
        await more.trigger('click');
        expect(getMock.mock.calls[1][0]).toBe(
            searchPhotos.url({ query: { search: 'new', page: 2 } }),
        );
        getMock.mock.calls[1][1].onSuccess({
            data: [truckPhoto({ id: 9 })],
            has_more: false,
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(3);
        await wrapper.get('input[type="checkbox"][value="7"]').setValue(false);
        expect(
            wrapper
                .findAll('input[name="photos[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['8']);
    });

    it('shows field errors and failed searches and disables loading actions', async () => {
        const wrapper = mountFields();
        await wrapper.setProps({
            errors: {
                name: 'Required name',
                description: 'Too long',
                'photos.0': 'Not an image',
            },
        });
        expect(wrapper.getComponent(FormField).props('error')).toBe(
            'Required name',
        );
        expect(wrapper.text()).toContain('Too long');
        expect(wrapper.text()).toContain('Not an image');
        await wrapper.get('button').trigger('click');
        expect(getMock.mock.calls[0][1].onNetworkError()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            t('store.trucks.fields.photosFailed'),
        );
        const http = useHttpMock.mock.results[0].value;
        http.processing = true;
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });
});
