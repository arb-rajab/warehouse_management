export interface Debounced<A extends unknown[]> {
    (...args: A): void;
    cancel(): void;
}

export function debounce<A extends unknown[]>(
    fn: (...args: A) => void,
    waitMs: number,
): Debounced<A> {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const debounced = (...args: A): void => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), waitMs);
    };

    debounced.cancel = (): void => {
        clearTimeout(timer);
        timer = undefined;
    };

    return debounced;
}
