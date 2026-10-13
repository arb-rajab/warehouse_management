import { describe, expect, it, vi } from 'vitest';
import type { InputKindName } from '../state/state';
import { InputKind } from './InputKind';

function pointerEvent(type: string, pointerType: string): Event {
    const event = new Event(type, { bubbles: true }) as Event & {
        pointerType: string;
    };

    event.pointerType = pointerType;

    return event;
}

describe('InputKind', () => {
    it('starts from the pointer media query', () => {
        expect(new InputKind(window, () => {}, true).current).toBe('touch');
        expect(new InputKind(window, () => {}, false).current).toBe('mouse');
    });

    it('follows whichever device was used last', () => {
        const changes: InputKindName[] = [];
        const kind = new InputKind(window, (k) => changes.push(k), false);

        kind.mount();
        window.dispatchEvent(pointerEvent('pointerdown', 'touch'));
        window.dispatchEvent(pointerEvent('pointerdown', 'touch'));
        window.dispatchEvent(pointerEvent('pointermove', 'mouse'));

        expect(changes).toEqual(['touch', 'mouse']);
        expect(kind.hasTouched).toBe(true);
        kind.dispose();
    });

    it('does not switch to mouse before any touch', () => {
        const onChange = vi.fn();
        const kind = new InputKind(window, onChange, false);

        kind.mount();
        window.dispatchEvent(pointerEvent('pointermove', 'mouse'));

        expect(onChange).not.toHaveBeenCalled();
        expect(kind.hasTouched).toBe(false);
        kind.dispose();
    });

    it('stops following after dispose', () => {
        const onChange = vi.fn();
        const kind = new InputKind(window, onChange, false);

        kind.mount();
        kind.dispose();
        window.dispatchEvent(pointerEvent('pointerdown', 'touch'));

        expect(onChange).not.toHaveBeenCalled();
    });
});
