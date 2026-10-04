import { onScopeDispose, ref } from 'vue';

/**
 * The "briefly highlight one cell" state the Cells map and the Row page both
 * use for a jump-to-cell: `pulse(label)` sets `pulsingLabel` and clears it
 * after `durationMs`, restarting the timer if called again meanwhile. The
 * pending timer is cleared when the owning component/scope is disposed, so
 * it can't write to a torn-down page.
 */
export function usePulse(durationMs = 1500) {
    const pulsingLabel = ref<string | null>(null);
    let timeout: ReturnType<typeof setTimeout> | undefined;

    function pulse(label: string): void {
        pulsingLabel.value = label;

        clearTimeout(timeout);
        timeout = setTimeout(() => {
            pulsingLabel.value = null;
        }, durationMs);
    }

    onScopeDispose(() => clearTimeout(timeout));

    return { pulsingLabel, pulse };
}
