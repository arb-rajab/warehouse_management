import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import type { Ref } from 'vue';
import { provideRequiredFields, useRequiredField } from './requiredFields';

const Field = defineComponent({
    props: { required: { type: Boolean, default: false } },
    setup(props) {
        useRequiredField(() => props.required);

        return () => h('input', { required: props.required });
    },
});

function mountForm(fields: Ref<boolean[]>) {
    let hasRequired: Ref<boolean> | undefined;
    const Form = defineComponent({
        setup() {
            hasRequired = provideRequiredFields();

            return () =>
                h(
                    'form',
                    fields.value.map((required) => h(Field, { required })),
                );
        },
    });
    mount(Form);

    return () => hasRequired!.value;
}

describe('requiredFields', () => {
    it('reports a required field rendered inside the providing form', async () => {
        const hasRequired = mountForm(ref([false, true]));
        await nextTick();

        expect(hasRequired()).toBe(true);
    });

    it('reports none when every field is optional', async () => {
        const hasRequired = mountForm(ref([false, false]));
        await nextTick();

        expect(hasRequired()).toBe(false);
    });

    it('follows a field that stops being required or is removed', async () => {
        const fields = ref([true, true]);
        const hasRequired = mountForm(fields);
        await nextTick();

        fields.value = [true, false];
        await nextTick();
        expect(hasRequired()).toBe(true);

        fields.value = [false];
        await nextTick();
        expect(hasRequired()).toBe(false);

        fields.value = [];
        await nextTick();
        expect(hasRequired()).toBe(false);
    });

    it('does nothing for a field outside any providing form', () => {
        expect(() => mount(Field, { props: { required: true } })).not.toThrow();
    });
});
