import { I18n } from '../i18n/I18n';
import type { Slice } from '../state/slices';

export interface FakeSlice<T> extends Slice<T> {
    set(next: Partial<T>): void;
}

/** A slice tests can push new values into. */
export function fakeSlice<T extends object>(initial: T): FakeSlice<T> {
    let state = initial;
    const listeners = new Set<(value: T, previous: T) => void>();

    return {
        get: () => state,
        subscribe: (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        set(next) {
            const previous = state;

            state = { ...state, ...next };
            listeners.forEach((listener) => listener(state, previous));
        },
    };
}

/** A translator whose messages are the keys (and their params), so tests read what was asked for. */
export function keyI18n(): I18n {
    return new Proxy(new I18n('en', {}), {
        get(target, property, receiver) {
            if (property === 't') {
                return (
                    key: string,
                    params?: Record<string, string | number>,
                ) =>
                    params ? `${key}(${Object.values(params).join(',')})` : key;
            }

            return Reflect.get(target, property, receiver);
        },
    });
}

export function host(): HTMLElement {
    const element = document.createElement('div');

    document.body.append(element);

    return element;
}

export function click(element: Element | null): void {
    element?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
}
