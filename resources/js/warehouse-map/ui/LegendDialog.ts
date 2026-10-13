import type { ViewKind } from '../state/state';
import { el } from '../utils/dom';
import { Dialog, dialogHead } from './Dialog';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface LegendState {
    open: boolean;
    view: ViewKind;
}

const ROWS: Array<{ swatch: string; label: string }> = [
    { swatch: 'full', label: 'legendSealed' },
    { swatch: 'opened', label: 'legendOpened' },
    { swatch: 'empty', label: 'legendVacant' },
    { swatch: 'target', label: 'legendTarget' },
    { swatch: 'inactive', label: 'legendInactive' },
];

/** What the colours mean. */
export class LegendDialog extends SliceView<LegendState> {
    private readonly dialog: Dialog;

    constructor(
        host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<LegendState>>[0],
        i18n: ConstructorParameters<typeof SliceView<LegendState>>[1],
        private readonly close: () => void,
    ) {
        super(slice, i18n);
        this.dialog = new Dialog(host, () => close(), 'small');
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

        const title = this.t(
            this.state.view === '3d' ? 'legend3d' : 'legendTitle',
        );
        const content = [
            dialogHead(
                [icon('info', 'wms-dialog__title-icon'), title],
                this.t('close'),
                () => this.close(),
                icon('x'),
            ),
            el(
                'div',
                { class: 'wms-dialog__body' },
                ...ROWS.map((row) =>
                    el(
                        'div',
                        { class: 'wms-legend-row' },
                        el('span', {
                            class: `wms-swatch wms-swatch--${row.swatch}`,
                        }),
                        this.t(row.label),
                    ),
                ),
            ),
        ];

        if (this.dialog.isOpen) {
            this.dialog.refresh(...content);
        } else {
            this.dialog.show(...content);
        }
    }
}
