import { ShieldCheck } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import UserRoleBadge from './UserRoleBadge.vue';

describe('UserRoleBadge', () => {
    it('shows the admin label with a shield icon for an admin user', () => {
        const wrapper = mount(UserRoleBadge, { props: { isAdmin: true } });

        expect(wrapper.text()).toBe(t('users.index.roleAdmin'));
        expect(wrapper.findComponent(ShieldCheck).exists()).toBe(true);
    });

    it('shows the mobile-user label with no shield icon for a non-admin user', () => {
        const wrapper = mount(UserRoleBadge, { props: { isAdmin: false } });

        expect(wrapper.text()).toBe(t('users.index.roleMobile'));
        expect(wrapper.findComponent(ShieldCheck).exists()).toBe(false);
    });
});
