import type { VueWrapper } from '@vue/test-utils';
import { expect } from 'vitest';
import RequiredMark from '@/components/RequiredMark.vue';

/** Assert the complete set of native requirements and labelled marks on a full mount. */
export function expectRequiredFields(wrapper: VueWrapper, ids: string[]): void {
    expect([
        ...new Set(
            wrapper
                .findAll(
                    'input[required], select[required], textarea[required], [aria-required="true"]',
                )
                .map(
                    (control) =>
                        control.attributes('id') ?? control.attributes('name'),
                )
                .sort(),
        ),
    ]).toEqual([...ids].sort());
    expect(
        wrapper
            .findAll('label, legend')
            .filter((label) => label.findComponent(RequiredMark).exists())
            .map(
                (label) =>
                    label.attributes('for') ??
                    label.element.parentElement
                        ?.querySelector('input[type=radio]')
                        ?.getAttribute('name'),
            )
            .sort(),
    ).toEqual([...ids].sort());
}
