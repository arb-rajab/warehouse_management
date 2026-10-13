import { View } from '../core/View';
import { el, replaceChildrenOf } from '../utils/dom';
import type { Child } from '../utils/dom';

const FOCUSABLE =
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export type DialogSize = 'default' | 'wide' | 'small';

/**
 * The shell shared by the rack, help and legend dialogs: a backdrop and a
 * dialog, closed by the backdrop or Escape, focus kept inside while open and
 * handed back to what had it when it closes. It only shows what it is given.
 */
export class Dialog extends View {
    readonly backdrop: HTMLElement;
    readonly panel: HTMLElement;
    private returnFocus: Element | null = null;
    private opened = false;

    constructor(
        private readonly host: HTMLElement,
        private readonly onClose: () => void,
        size: DialogSize = 'default',
        private readonly label = '',
    ) {
        super();
        this.panel = el('div', {
            class: `wms-dialog${size === 'default' ? '' : ` wms-dialog--${size}`}`,
            attrs: {
                role: 'dialog',
                'aria-modal': 'true',
                tabindex: '-1',
                'aria-label': label || null,
            },
        });
        this.backdrop = el('div', { class: 'wms-backdrop' }, this.panel);
    }

    get isOpen(): boolean {
        return this.opened;
    }

    mount(): void {
        this.host.append(this.backdrop);
        this.on(this.backdrop, 'pointerdown', (e: Event) => {
            if (e.target === this.backdrop) {
                this.onClose();
            }
        });
        this.on(this.panel, 'keydown', (e: KeyboardEvent) => this.trapTab(e));
    }

    protected onDispose(): void {
        this.backdrop.remove();
    }

    /** Replaces the content; shows the dialog if it was closed. */
    show(...content: Child[]): void {
        replaceChildrenOf(this.panel, ...content);

        if (!this.opened) {
            this.opened = true;
            this.returnFocus = document.activeElement;
            this.backdrop.classList.add('is-open');
            this.panel.focus({ preventScroll: true });
        }
    }

    /** Replaces the content of an open dialog without moving the focus (a language change, a setting). */
    refresh(...content: Child[]): void {
        const scroll =
            this.panel.querySelector('.wms-dialog__body')?.scrollTop ?? 0;

        replaceChildrenOf(this.panel, ...content);

        const body = this.panel.querySelector('.wms-dialog__body');

        if (body) {
            body.scrollTop = scroll;
        }
    }

    hide(): void {
        if (!this.opened) {
            return;
        }

        this.opened = false;
        this.backdrop.classList.remove('is-open');

        const target = this.returnFocus;

        this.returnFocus = null;

        if (target instanceof HTMLElement && document.contains(target)) {
            target.focus({ preventScroll: true });
        }
    }

    private trapTab(e: KeyboardEvent): void {
        if (e.key !== 'Tab') {
            return;
        }

        const items = [
            ...this.panel.querySelectorAll<HTMLElement>(FOCUSABLE),
        ].filter((n) => !n.hasAttribute('disabled'));

        if (items.length === 0) {
            e.preventDefault();

            return;
        }

        const first = items[0];
        const last = items[items.length - 1];

        if (
            e.shiftKey &&
            (document.activeElement === first ||
                document.activeElement === this.panel)
        ) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

/** The title row of a dialog: heading + close button. */
export function dialogHead(
    title: Child[],
    closeLabel: string,
    onClose: () => void,
    icon: Node,
): HTMLElement {
    const close = el(
        'button',
        {
            class: 'wms-dialog__x',
            attrs: { type: 'button', 'aria-label': closeLabel },
        },
        icon,
    );

    close.addEventListener('click', onClose);

    return el(
        'div',
        { class: 'wms-dialog__head' },
        el('h2', { class: 'wms-dialog__title' }, ...title),
        close,
    );
}
