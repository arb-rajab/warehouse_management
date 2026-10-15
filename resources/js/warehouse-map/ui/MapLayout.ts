import { View } from '../core/View';
import { el } from '../utils/dom';
import { iconSprite } from './icons';

/**
 * The page skeleton inside the host: the two stages (the 2D plan and the 3D
 * canvas), the bar for the camera and the result pill, the layer for the
 * floating overlays. Views mount their own pieces into these.
 */
export class MapLayout extends View {
    readonly root = el('div', { class: 'wms' });
    readonly planStage = el('div', { class: 'wms-stage wms-stage--plan' });
    readonly canvas = el('canvas', {
        class: 'wms-canvas',
        attrs: { tabindex: 0, 'aria-label': 'Warehouse 3D view' },
    });
    readonly stage3d = el(
        'div',
        { class: 'wms-stage wms-stage--3d' },
        this.canvas,
    );
    readonly subbar = el('div', { class: 'wms-subbar wms-hud' });

    constructor(private readonly host: HTMLElement) {
        super();
    }

    mount(): void {
        this.root.append(iconSprite(), this.planStage, this.stage3d);
        this.root.append(this.subbar);
        this.host.append(this.root);
    }

    protected onDispose(): void {
        this.root.remove();
    }
}
