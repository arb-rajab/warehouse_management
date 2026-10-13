import { describe, expect, it, vi } from 'vitest';
import { Refetcher } from './Refetcher';

function setup(minInterval = 1000) {
    let clock = 0;
    const refresh = vi.fn().mockResolvedValue(undefined);
    const refetcher = new Refetcher(
        document,
        refresh,
        () => clock,
        minInterval,
    );

    refetcher.mount();

    return {
        refresh,
        refetcher,
        advance: (ms: number) => (clock += ms),
        comeBack: () => {
            Object.defineProperty(document, 'visibilityState', {
                configurable: true,
                value: 'visible',
            });
            document.dispatchEvent(new Event('visibilitychange'));
        },
    };
}

describe('Refetcher', () => {
    it('revalidates when the tab comes back after a while away', async () => {
        const { refresh, advance, comeBack } = setup();

        advance(2000);
        comeBack();
        await Promise.resolve();

        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('does not hammer the server when the tab flickers', async () => {
        const { refresh, advance, comeBack } = setup();

        advance(500);
        comeBack();
        await Promise.resolve();
        expect(refresh).not.toHaveBeenCalled();

        advance(600);
        comeBack();
        comeBack();
        await Promise.resolve();
        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('also revalidates when the window regains focus', async () => {
        const { refresh, advance } = setup();

        advance(5000);
        window.dispatchEvent(new Event('focus'));
        await Promise.resolve();

        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('treats a fresh load as recent', async () => {
        const { refresh, refetcher, advance } = setup();

        advance(5000);
        refetcher.markFresh();
        await refetcher.maybeRefresh();

        expect(refresh).not.toHaveBeenCalled();
    });

    it('survives a failing refresh and tries again next time', async () => {
        const { refresh, refetcher, advance } = setup();

        refresh.mockRejectedValueOnce(new Error('offline'));
        advance(5000);
        await refetcher.maybeRefresh();
        advance(5000);
        await refetcher.maybeRefresh();

        expect(refresh).toHaveBeenCalledTimes(2);
    });

    it('stops after dispose', async () => {
        const { refresh, refetcher, advance, comeBack } = setup();

        refetcher.dispose();
        advance(5000);
        comeBack();
        await Promise.resolve();

        expect(refresh).not.toHaveBeenCalled();
    });
});
