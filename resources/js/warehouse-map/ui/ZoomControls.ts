import type { PlanControls } from '../plan2d/PlanView';
import { el } from '../utils/dom';
import { icon } from './icons';
import type { IconName } from './icons';
import { SliceView } from './SliceView';

/** Touch only: zoom, fit and rotate buttons for the 2D plan (a phone has no wheel or keys). */
export class ZoomControls extends SliceView<Record<string, never>> {
    readonly element = el('div', {
        class: 'wms-zoom wms-hud wms-pill wms-touch-only',
    });
    private readonly buttons: Array<{
        button: HTMLButtonElement;
        label: string;
    }> = [];

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<
            typeof SliceView<Record<string, never>>
        >[0],
        i18n: ConstructorParameters<typeof SliceView<Record<string, never>>>[1],
        private readonly plan: PlanControls,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        const make = (name: IconName, label: string, action: () => void) => {
            const button = el(
                'button',
                { class: 'wms-icon-btn', attrs: { type: 'button' } },
                icon(name),
            );

            this.on(button, 'click', action);
            this.buttons.push({ button, label });
            this.element.append(button);
        };

        make('plus', 'zoomIn', () => this.plan.zoomBy(1.35, true));
        make('fit', 'fitPlan', () => this.plan.fit(true));
        make('rotr', 'rotatePlan', () => this.plan.rotate());
        make('minus', 'zoomOut', () => this.plan.zoomBy(1 / 1.35, true));
        this.host.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        for (const { button, label } of this.buttons) {
            button.setAttribute('aria-label', this.t(label));
            button.title = this.t(label);
        }
    }
}
