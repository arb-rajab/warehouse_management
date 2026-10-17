import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { toggleActive } from '@/actions/App/Http/Controllers/Admin/CellController';
import FilterDialog from '@/components/FilterDialog.vue';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';
import ActionErrorBanner from './ActionErrorBanner.vue';
import SubmitButton from './SubmitButton.vue';
import ToggleCellActiveDialog from './ToggleCellActiveDialog.vue';

const { formSlotPropsMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(formSlotPropsMock) };
});

function cell(isActive: boolean): Cell {
    return {
        id: 7,
        cell_number: 2,
        flat_number: 1,
        state: 'empty',
        is_active: isActive,
        pallet: null,
    };
}

function mountDialog(
    props: Partial<{
        cell: Cell | null;
        'onUpdate:open': (open: boolean) => void;
    }> = {},
) {
    return mount(ToggleCellActiveDialog, {
        props: {
            cell: 'cell' in props ? (props.cell as Cell | null) : cell(true),
            label: 'A2·1',
            open: true,
            'onUpdate:open': props['onUpdate:open'] ?? (() => {}),
        },
    });
}

describe('ToggleCellActiveDialog', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('labels its close button with the shared action-dialog close text', () => {
        const wrapper = mountDialog();

        expect(t('common.close')).toBe('Close');
        expect(wrapper.findComponent(FilterDialog).props('closeLabel')).toBe(
            t('common.close'),
        );
    });

    it('shows the deactivate title/confirm/submit text for an active cell', () => {
        const wrapper = mountDialog({ cell: cell(true) });

        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateTitle', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateConfirm', { location: 'A2·1' }),
        );
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            t('cells.toggleActive.submitDeactivate'),
        );
    });

    it('shows the reactivate title/confirm/submit text for an inactive cell', () => {
        const wrapper = mountDialog({ cell: cell(false) });

        expect(wrapper.text()).toContain(
            t('cells.toggleActive.reactivateTitle', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.reactivateConfirm', { location: 'A2·1' }),
        );
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            t('cells.toggleActive.submitReactivate'),
        );
    });

    it('renders the note label', () => {
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain(t('cells.toggleActive.noteLabel'));
    });

    it('posts to the cell’s toggle-active route, preserving scroll', () => {
        const wrapper = mountDialog();

        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual(
            toggleActive(7, { mergeQuery: {} }),
        );
        expect(form.props('action')).toMatchObject({ method: 'post' });
        expect(form.props('options')).toEqual({ preserveScroll: true });
    });

    it('names the note field so the form submits it', () => {
        const wrapper = mountDialog();

        const note = wrapper.get('textarea');
        expect(note.attributes('name')).toBe('note');
        expect(note.attributes('maxlength')).toBe('1000');
    });

    it('sends no return_to field', () => {
        const wrapper = mountDialog();

        expect(wrapper.find('input[name="return_to"]').exists()).toBe(false);
    });

    it('renders no form when there is no cell', () => {
        const wrapper = mountDialog({ cell: null });

        expect(wrapper.findComponent(Form).exists()).toBe(false);
    });

    it('closes the dialog when the form succeeds', async () => {
        const updateOpen = vi.fn();
        const wrapper = mountDialog({ 'onUpdate:open': updateOpen });

        wrapper.getComponent(Form).vm.$emit('success');
        await wrapper.vm.$nextTick();

        expect(updateOpen).toHaveBeenCalledWith(false);
    });

    it('does not close the dialog until the form succeeds', () => {
        const updateOpen = vi.fn();
        mountDialog({ 'onUpdate:open': updateOpen });

        expect(updateOpen).not.toHaveBeenCalled();
    });

    it('passes the form’s processing state to the submit button', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });
        const wrapper = mountDialog();

        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });

    it('shows the form’s first server error in a banner', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { note: 'Too long' },
            processing: false,
        });
        const wrapper = mountDialog();

        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'Too long',
        );
    });

    it('shows no error message when the form has none', () => {
        const wrapper = mountDialog();

        expect(
            wrapper.getComponent(ActionErrorBanner).props('message'),
        ).toBeUndefined();
    });
});
