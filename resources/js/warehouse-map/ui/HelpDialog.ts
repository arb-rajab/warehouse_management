import type { UserSettings } from '../config';
import type { KeyHintKind } from '../state/selectors';
import { el } from '../utils/dom';
import type { Child } from '../utils/dom';
import { Dialog, dialogHead } from './Dialog';
import { icon } from './icons';
import { keyCaps, keyRows } from './keyHints';
import type { KeyRow } from './keyHints';
import { SliceView } from './SliceView';

export interface HelpState {
    open: boolean;
    inputKind: 'mouse' | 'touch';
    aisles: number;
}

export interface HelpIntents {
    close(): void;
    settings(): UserSettings;
    update(patch: Partial<UserSettings>): void;
}

const TOUCH_ROWS = [
    'tOrbit',
    'tPanZoom',
    'tTapPallet',
    'tStick',
    'tLook',
    'tPad',
    'tPan',
    'tZoom2',
    'tTap2',
];

const SECTIONS: Array<{ kind: KeyHintKind; title: string }> = [
    { kind: 'plan', title: 'hSec2d' },
    { kind: 'overview', title: 'hSecOver' },
    { kind: 'free', title: 'hSecFree' },
    { kind: 'guided', title: 'hSecGuided' },
];

/** All the controls, and the settings (sensitivity, speed, field of view, ...), remembered per viewer. */
export class HelpDialog extends SliceView<HelpState> {
    private readonly dialog: Dialog;
    private idCounter = 0;

    constructor(
        host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<HelpState>>[0],
        i18n: ConstructorParameters<typeof SliceView<HelpState>>[1],
        private readonly intents: HelpIntents,
    ) {
        super(slice, i18n);
        this.dialog = new Dialog(host, () => intents.close(), 'wide');
    }

    protected build(): void {
        this.dialog.mount();
        this.own(() => this.dialog.dispose());
    }

    protected render(): void {
        if (!this.state.open) {
            this.dialog.hide();

            return;
        }

        const content = [
            dialogHead(
                [this.t('help')],
                this.t('close'),
                () => this.intents.close(),
                icon('x'),
            ),
            el(
                'div',
                { class: 'wms-dialog__body' },
                ...this.keysBlock(),
                this.settingsBlock(),
            ),
        ];

        if (this.dialog.isOpen) {
            this.dialog.refresh(...content);
        } else {
            this.dialog.show(...content);
        }
    }

    private section(
        title: string,
        rows: Array<{ keys: Child[]; description: Child }>,
    ): HTMLElement {
        return el(
            'div',
            { class: 'wms-hsec' },
            el('h3', { class: 'wms-hsec__title', text: title }),
            el(
                'div',
                { class: 'wms-hgrid' },
                ...rows.map((r) =>
                    el(
                        'div',
                        { class: 'wms-hrow' },
                        el('span', { class: 'wms-hrow__keys' }, ...r.keys),
                        el('span', { class: 'wms-hrow__desc' }, r.description),
                    ),
                ),
            ),
        );
    }

    private keysBlock(): HTMLElement[] {
        const t = (key: string) => this.t(key);

        if (this.state.inputKind === 'touch') {
            return [
                this.section(
                    t('hSecTouch'),
                    TOUCH_ROWS.map((key) => ({
                        keys: [t(key)],
                        description: '',
                    })),
                ),
            ];
        }

        return SECTIONS.map(({ kind, title }) =>
            this.section(
                t(title),
                keyRows(kind, this.state.aisles, false).map((row: KeyRow) => ({
                    keys: keyCaps(row.keys, t),
                    description: t(row.description),
                })),
            ),
        );
    }

    private range(
        label: string,
        key: 'sens' | 'speed' | 'fov',
        min: number,
        max: number,
        step: number,
        format: (v: number) => string,
    ): Child[] {
        const id = `wms-cfg-${key}-${this.idCounter++}`;
        const settings = this.intents.settings();
        const input = el('input', {
            attrs: { id, type: 'range', min, max, step, value: settings[key] },
        });
        const output = el('output', {
            attrs: { for: id },
            text: format(settings[key]),
        });

        this.on(input, 'input', () => {
            const value = Number(input.value);

            output.textContent = format(value);
            this.intents.update({ [key]: value });
        });

        return [
            el('label', { attrs: { for: id }, text: label }),
            el(
                'span',
                { class: 'wms-range' },
                el('small', { text: format(min) }),
                input,
                el('small', { text: format(max) }),
                output,
            ),
        ];
    }

    private switchRow(
        label: string,
        key: 'invertY' | 'bob' | 'magnet',
    ): Child[] {
        const id = `wms-cfg-${key}-${this.idCounter++}`;
        const input = el('input', {
            class: 'wms-switch',
            attrs: { id, type: 'checkbox' },
        });

        input.checked = this.intents.settings()[key];
        this.on(input, 'change', () =>
            this.intents.update({ [key]: input.checked }),
        );

        return [el('label', { attrs: { for: id }, text: label }), input];
    }

    private settingsBlock(): HTMLElement {
        const t = (key: string) => this.t(key);
        const times = (v: number) => `${v.toFixed(2)}×`;
        const degrees = (v: number) => `${v}°`;

        return el(
            'div',
            { class: 'wms-hsec' },
            el('h3', { class: 'wms-hsec__title', text: t('hSecSettings') }),
            el(
                'div',
                { class: 'wms-set' },
                ...this.range(t('sSens'), 'sens', 0.4, 2.5, 0.05, times),
                ...this.range(t('sSpeed'), 'speed', 0.5, 2, 0.05, times),
                ...this.range(t('sFov'), 'fov', 45, 90, 1, degrees),
                ...this.switchRow(t('sInvert'), 'invertY'),
                ...this.switchRow(t('sBob'), 'bob'),
                ...this.switchRow(t('sMagnet'), 'magnet'),
            ),
        );
    }
}
