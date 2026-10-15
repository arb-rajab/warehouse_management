import { THRESHOLDS } from '../config';
import type { KeyHintKind } from '../state/selectors';
import { el } from '../utils/dom';
import { keyCaps, keyRows } from './keyHints';
import { SliceView } from './SliceView';

export interface KeysState {
    kind: KeyHintKind;
    aisles: number;
    /** True once the walk begins: the panel shows, then fades away by itself. */
    walking: boolean;
}

/** The compact key hints in the corner of the 3D view (desktop only). They fade after a while; H brings them back. */
export class KeysPanel extends SliceView<KeysState> {
    readonly element = el('div', { class: 'wms-keys wms-hud wms-pill' });
    private fadeTimer: ReturnType<typeof setTimeout> | undefined;
    private wasWalking = false;

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<KeysState>>[0],
        i18n: ConstructorParameters<typeof SliceView<KeysState>>[1],
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.host.append(this.element);
        this.own(() => clearTimeout(this.fadeTimer));
    }

    protected onDispose(): void {
        this.element.remove();
    }

    /** H: fade or bring back the panel. */
    toggle(): void {
        clearTimeout(this.fadeTimer);
        this.element.classList.toggle('is-faded');
    }

    protected render(): void {
        const { kind, aisles, walking } = this.state;
        const t = (key: string) => this.t(key);

        this.element.replaceChildren(
            el('div', { class: 'wms-keys__head', text: t('help') }),
            ...keyRows(kind, aisles, true).map((row) =>
                el(
                    'div',
                    { class: 'wms-krow' },
                    el(
                        'span',
                        { class: 'wms-krow__keys' },
                        ...keyCaps(row.keys, t),
                    ),
                    el('span', {
                        class: 'wms-krow__desc',
                        text: t(row.description),
                    }),
                ),
            ),
        );

        if (walking && !this.wasWalking) {
            this.element.classList.remove('is-faded');
            clearTimeout(this.fadeTimer);
            this.fadeTimer = setTimeout(
                () => this.element.classList.add('is-faded'),
                THRESHOLDS.keysFadeMs,
            );
        }

        this.wasWalking = walking;
    }
}
