import { CircleHelp } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { show as helpShow } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { t } from '@/lib/i18n';
import HelpLink from './HelpLink.vue';

describe('HelpLink', () => {
    it('renders a link to the given href with a help icon', () => {
        const wrapper = mount(HelpLink, {
            props: { href: helpShow.url({ topic: 'rows' }) },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe(helpShow.url({ topic: 'rows' }));
        expect(wrapper.findComponent(CircleHelp).exists()).toBe(true);
    });

    it('accepts a wayfinder url/method pair as the href', () => {
        const wrapper = mount(HelpLink, {
            props: {
                href: { url: helpShow.url({ topic: 'users' }), method: 'get' },
            },
        });

        expect(wrapper.get('a').attributes('href')).toBe(
            helpShow.url({ topic: 'users' }),
        );
    });

    it('labels the link for accessibility', () => {
        const wrapper = mount(HelpLink, {
            props: { href: helpShow.url({ topic: 'rows' }) },
        });

        expect(wrapper.get('a').attributes('aria-label')).toBe(
            t('help.viewHelp'),
        );
    });
});
