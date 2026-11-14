import type { Locale, WarehouseOption } from './domain/types';
import type { WarehouseSelection } from './domain/warehouseSelection';
import { I18n } from './i18n/I18n';
import type { Messages } from './i18n/I18n';
import { icon, iconSprite } from './ui/icons';
import { WarehouseSwitcher } from './ui/WarehouseSwitcher';
import { el } from './utils/dom';

export interface SwitcherPreviewOptions {
    locale: Locale;
    /** The `warehouseMap` messages of the current language. */
    messages: Messages;
    fallbackMessages?: Messages;
    options: readonly WarehouseOption[];
    selected: WarehouseSelection;
}

/**
 * The toolbar's warehouse switcher on its own, held open, for the help page:
 * the real view and styles in a `.wms--preview` root (in the page's flow,
 * desktop layout), inside the brand pill as the map shows it. A choice in it
 * asks for nothing.
 */
export function mountWarehouseSwitcherPreview(
    host: HTMLElement,
    options: SwitcherPreviewOptions,
): { dispose(): void } {
    const i18n = new I18n(
        options.locale,
        options.messages,
        options.fallbackMessages ?? options.messages,
    );
    const state = { options: options.options, selected: options.selected };
    const switcher = new WarehouseSwitcher(
        { get: () => state, subscribe: () => () => undefined },
        i18n,
        { apply: () => undefined },
    );
    const root = el('div', {
        class: 'wms wms--preview',
        attrs: { dir: i18n.dir, lang: options.locale },
    });

    root.append(
        iconSprite(),
        el(
            'div',
            { class: 'wms-pill wms-bar wms-brand has-warehouses' },
            icon('warehouse', 'wms-brand__logo'),
            switcher.element,
        ),
    );
    host.append(root);
    switcher.mount();
    switcher.pinOpen();

    return {
        dispose(): void {
            switcher.dispose();
            root.remove();
        },
    };
}
