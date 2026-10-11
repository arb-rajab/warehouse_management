import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import { storeOffer } from '@/testing/factories';
import StoreCampaignFields from './StoreCampaignFields.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock, processing: false, errors: {}, q: '' }),
}));
beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockReturnValue(Promise.resolve());
});

it('passes image upload state and labels and mirrors date and title rules with second precision', async () => {
    const offer = storeOffer();
    const wrapper = mount(StoreCampaignFields, {
        props: { campaign: offer, titleMax: 255, errors: { title: 'Missing' } },
    });

    for (const key of ['title', 'banner', 'startDate', 'endDate']) {
        expect(wrapper.text()).toContain(t(`store.campaigns.fields.${key}`));
    }

    expect(wrapper.get('#title').attributes()).toMatchObject({
        maxlength: '255',
        required: '',
    });
    expect(wrapper.text()).toContain('Missing');
    expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
        modelValue: '',
        type: 'image',
        name: 'banner',
    });
    expect(wrapper.get('#start_date').attributes()).toMatchObject({
        type: 'datetime-local',
        step: '1',
        max: offer.end_date,
        required: '',
    });
    await wrapper.get('#end_date').setValue('2026-11-01T08:09:10');
    expect(Date.parse(wrapper.get('#start_date').attributes('max')!)).toBe(
        Date.parse('2026-11-01T08:09:10'),
    );
});
