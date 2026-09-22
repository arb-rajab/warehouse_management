import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';
import SubmitButton from './SubmitButton.vue';
import ToggleCellActiveDialog from './ToggleCellActiveDialog.vue';

const { routerPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
}));

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

/**
 * Mounts closed and transitions to open, matching how the dialog is always
 * used in the real app (Rows/Show.vue starts it at `open: false` and flips
 * it to `true` later) — the note field is only reset by the `watch(open,
 * ...)` handler, which needs a false→true transition to fire (see
 * PalletActionsDialog.test.ts for the same pattern).
 */
async function mountDialog(
    props: Partial<{
        cell: Cell | null;
        label: string;
        returnTo: 'row';
    }> = {},
) {
    const wrapper = mount(ToggleCellActiveDialog, {
        props: {
            cell: 'cell' in props ? (props.cell as Cell | null) : cell(true),
            label: props.label ?? 'A2·1',
            returnTo: props.returnTo,
            open: false,
            'onUpdate:open': () => {},
        },
    });

    await wrapper.setProps({ open: true });

    return wrapper;
}

describe('ToggleCellActiveDialog', () => {
    beforeEach(() => {
        routerPostMock.mockReset();
    });

    it('shows the deactivate title/confirm/submit text for an active cell', async () => {
        const wrapper = await mountDialog({ cell: cell(true) });

        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateTitle', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateConfirm', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.submitDeactivate'),
        );
    });

    it('shows the reactivate title/confirm/submit text for an inactive cell', async () => {
        const wrapper = await mountDialog({ cell: cell(false) });

        expect(wrapper.text()).toContain(
            t('cells.toggleActive.reactivateTitle', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.reactivateConfirm', { location: 'A2·1' }),
        );
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.submitReactivate'),
        );
    });

    it('resets the note field whenever the dialog is reopened', async () => {
        const wrapper = mount(ToggleCellActiveDialog, {
            props: {
                cell: cell(true),
                label: 'A2·1',
                open: false,
                'onUpdate:open': () => {},
            },
        });

        await wrapper.setProps({ open: true });
        await wrapper.get('textarea').setValue('Damaged shelf');
        expect(
            (wrapper.get('textarea').element as HTMLTextAreaElement).value,
        ).toBe('Damaged shelf');

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });

        expect(
            (wrapper.get('textarea').element as HTMLTextAreaElement).value,
        ).toBe('');
    });

    it('posts note null and return_to null by default', async () => {
        const wrapper = await mountDialog({ cell: cell(true) });

        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][0]).toContain(
            'cells/7/toggle-active',
        );
        expect(routerPostMock.mock.calls[0][1]).toEqual({
            note: null,
            return_to: null,
        });
    });

    it('posts the typed note and return_to when provided', async () => {
        const wrapper = await mountDialog({
            cell: cell(true),
            returnTo: 'row',
        });

        await wrapper.get('textarea').setValue('Damaged shelf');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toEqual({
            note: 'Damaged shelf',
            return_to: 'row',
        });
    });

    it('does not submit when there is no cell', async () => {
        const wrapper = await mountDialog({ cell: null });

        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).not.toHaveBeenCalled();
    });

    it('closes the dialog on success, and stops processing regardless once the request finishes', async () => {
        const updateOpen = vi.fn();
        const wrapper = mount(ToggleCellActiveDialog, {
            props: {
                cell: cell(true),
                label: 'A2·1',
                open: false,
                'onUpdate:open': updateOpen,
            },
        });
        await wrapper.setProps({ open: true });

        await wrapper.get('form').trigger('submit');

        const options = routerPostMock.mock.calls[0][2] as {
            onSuccess: () => void;
            onFinish: () => void;
        };
        options.onSuccess();
        await wrapper.vm.$nextTick();

        expect(updateOpen).toHaveBeenCalledWith(false);

        options.onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            false,
        );
    });

    it('keeps the dialog open when the request finishes without succeeding', async () => {
        const updateOpen = vi.fn();
        const wrapper = mount(ToggleCellActiveDialog, {
            props: {
                cell: cell(true),
                label: 'A2·1',
                open: false,
                'onUpdate:open': updateOpen,
            },
        });
        await wrapper.setProps({ open: true });

        await wrapper.get('form').trigger('submit');

        // A failed request (e.g. a 419 after session expiry) calls onFinish
        // without ever calling onSuccess — the dialog must not close as if
        // the action had actually gone through.
        const options = routerPostMock.mock.calls[0][2] as {
            onFinish: () => void;
        };
        options.onFinish();

        expect(updateOpen).not.toHaveBeenCalledWith(false);
    });
});
