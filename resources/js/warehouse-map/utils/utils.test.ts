import { afterEach, describe, expect, it, vi } from 'vitest';
import { View } from '../core/View';
import { copyText } from './clipboard';
import { debounce } from './debounce';
import {
    append,
    el,
    isTypingTarget,
    kbd,
    ltr,
    replaceChildrenOf,
    setAttr,
} from './dom';
import { angleDelta, clamp, easeInOutCubic, lerp, lerpAngle } from './math';

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('math', () => {
    it('clamps, interpolates and eases', () => {
        expect(clamp(5, 0, 3)).toBe(3);
        expect(clamp(-1, 0, 3)).toBe(0);
        expect(lerp(10, 20, 0.25)).toBe(12.5);
        expect(easeInOutCubic(0)).toBe(0);
        expect(easeInOutCubic(1)).toBe(1);
        expect(easeInOutCubic(0.5)).toBeCloseTo(0.5);
        expect(easeInOutCubic(0.25)).toBeLessThan(0.25);
    });

    it('turns the short way round', () => {
        expect(angleDelta(0.1, -0.1)).toBeCloseTo(-0.2);
        expect(angleDelta(3, -3)).toBeCloseTo(2 * Math.PI - 6);
        expect(lerpAngle(3, -3, 0.5)).toBeCloseTo(3 + (2 * Math.PI - 6) / 2);
    });
});

describe('debounce', () => {
    it('calls once after the pause with the last arguments, and can be cancelled', () => {
        vi.useFakeTimers();

        const fn = vi.fn();
        const debounced = debounce(fn, 100);

        debounced(1);
        debounced(2);
        vi.advanceTimersByTime(99);
        expect(fn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(fn).toHaveBeenCalledTimes(1);
        expect(fn).toHaveBeenCalledWith(2);

        debounced(3);
        debounced.cancel();
        vi.advanceTimersByTime(500);
        expect(fn).toHaveBeenCalledTimes(1);
    });
});

describe('dom helpers', () => {
    it('builds elements with text as text, never as HTML', () => {
        const node = el(
            'div',
            {
                class: 'a',
                text: '<b>x</b>',
                attrs: { title: 't', hidden: false, 'data-n': 3 },
                data: { row: 'A', gone: null },
                vars: { '--x': 2 },
            },
            'tail',
            null,
            false,
            7,
        );

        expect(node.className).toBe('a');
        expect(node.querySelector('b')).toBeNull();
        expect(node.textContent).toBe('<b>x</b>tail7');
        expect(node.getAttribute('title')).toBe('t');
        expect(node.hasAttribute('hidden')).toBe(false);
        expect(node.dataset.row).toBe('A');
        expect(node.style.getPropertyValue('--x')).toBe('2');
    });

    it('sets and removes attributes, replaces children and isolates left-to-right text', () => {
        const node = el('p');

        setAttr(node, 'aria-hidden', true);
        expect(node.getAttribute('aria-hidden')).toBe('');
        setAttr(node, 'aria-hidden', null);
        expect(node.hasAttribute('aria-hidden')).toBe(false);

        append(node, 'a', el('i'));
        replaceChildrenOf(node, 'b');
        expect(node.innerHTML).toBe('b');

        expect(ltr('A5').getAttribute('dir')).toBe('ltr');
        expect(kbd('W', 'A').map((k) => k.tagName)).toEqual(['KBD', 'KBD']);
    });

    it('knows when you are typing', () => {
        const input = document.createElement('input');
        const range = document.createElement('input');
        const box = document.createElement('input');
        const area = document.createElement('textarea');
        const div = document.createElement('div');

        range.type = 'range';
        box.type = 'checkbox';
        expect(isTypingTarget(input)).toBe(true);
        expect(isTypingTarget(area)).toBe(true);
        expect(isTypingTarget(range)).toBe(false);
        expect(isTypingTarget(box)).toBe(false);
        expect(isTypingTarget(div)).toBe(false);
        expect(isTypingTarget(null)).toBe(false);
    });
});

describe('copyText', () => {
    it('copies, and reports false when the browser refuses or cannot', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', { clipboard: { writeText } });
        expect(await copyText('x')).toBe(true);
        expect(writeText).toHaveBeenCalledWith('x');

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: vi.fn().mockRejectedValue(new Error('no')),
            },
        });
        expect(await copyText('x')).toBe(false);

        vi.stubGlobal('navigator', {});
        expect(await copyText('x')).toBe(false);
    });
});

describe('View', () => {
    class Probe extends View {
        released = false;
        mount(): void {}
        listen(target: EventTarget, handler: () => void): void {
            this.on(target, 'ping', handler);
        }
        keep(fn: () => void): void {
            this.own(fn);
        }
        protected onDispose(): void {
            this.released = true;
        }
    }

    it('removes its listeners and runs its cleanups once, last registered first', () => {
        const probe = new Probe();
        const target = new EventTarget();
        const handler = vi.fn();
        const order: number[] = [];

        probe.listen(target, handler);
        probe.keep(() => order.push(1));
        probe.keep(() => order.push(2));
        target.dispatchEvent(new Event('ping'));
        expect(handler).toHaveBeenCalledTimes(1);

        probe.dispose();
        probe.dispose();
        target.dispatchEvent(new Event('ping'));

        expect(handler).toHaveBeenCalledTimes(1);
        expect(order).toEqual([2, 1]);
        expect(probe.released).toBe(true);
    });
});
