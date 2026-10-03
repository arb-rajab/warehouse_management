import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { usePulse } from './usePulse';

describe('usePulse', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts with nothing pulsing', () => {
        const scope = effectScope();
        const { pulsingLabel } = scope.run(() => usePulse())!;

        expect(pulsingLabel.value).toBeNull();
        scope.stop();
    });

    it('pulses a label and clears it after the default 1500ms', () => {
        const scope = effectScope();
        const { pulsingLabel, pulse } = scope.run(() => usePulse())!;

        pulse('A-1-1');
        expect(pulsingLabel.value).toBe('A-1-1');

        vi.advanceTimersByTime(1499);
        expect(pulsingLabel.value).toBe('A-1-1');

        vi.advanceTimersByTime(1);
        expect(pulsingLabel.value).toBeNull();
        scope.stop();
    });

    it('honours a custom duration', () => {
        const scope = effectScope();
        const { pulsingLabel, pulse } = scope.run(() => usePulse(300))!;

        pulse('A-1-1');
        vi.advanceTimersByTime(300);

        expect(pulsingLabel.value).toBeNull();
        scope.stop();
    });

    it('restarts the timer when pulsed again, so the earlier timeout cannot clear the newer label', () => {
        const scope = effectScope();
        const { pulsingLabel, pulse } = scope.run(() => usePulse())!;

        pulse('A-1-1');
        vi.advanceTimersByTime(1000);
        pulse('B-2-2');
        vi.advanceTimersByTime(1000);

        expect(pulsingLabel.value).toBe('B-2-2');

        vi.advanceTimersByTime(500);
        expect(pulsingLabel.value).toBeNull();
        scope.stop();
    });

    it('clears the pending timer when the scope is disposed', () => {
        const scope = effectScope();
        const { pulsingLabel, pulse } = scope.run(() => usePulse())!;

        pulse('A-1-1');
        expect(vi.getTimerCount()).toBe(1);

        scope.stop();

        expect(vi.getTimerCount()).toBe(0);
        vi.advanceTimersByTime(5000);
        expect(pulsingLabel.value).toBe('A-1-1');
    });
});
