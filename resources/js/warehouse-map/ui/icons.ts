const SVG_NS = 'http://www.w3.org/2000/svg';

/** 24x24 stroke icons: path data for the sprite every `icon()` points into. */
const SHAPES: Record<string, string[]> = {
    search: ['circle:11,11,7', 'M20 20l-3.5-3.5'],
    x: ['M18 6L6 18M6 6l12 12'],
    info: ['circle:12,12,9', 'M12 16v-5M12 8h.01'],
    sun: [
        'circle:12,12,4',
        'M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
    ],
    clock: ['circle:12,12,9', 'M12 7v5l3 2'],
    box: ['M21 8L12 3 3 8v8l9 5 9-5z', 'M3 8l9 5 9-5M12 13v8'],
    plus: ['M12 5v14M5 12h14'],
    minus: ['M5 12h14'],
    fit: ['M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5'],
    back: ['M15 18l-6-6 6-6'],
    map: ['M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z', 'M9 4v14M15 6v14'],
    cam: ['rect:2,6,14,12,2', 'M16 10l6-3v10l-6-3'],
    walk: ['circle:13,4,2', 'M9 22l2-7-3-3 3-5 3 3 3 1M11 15l3 3 1 4M8 9l-3 3'],
    up: ['M6 15l6-6 6 6'],
    down: ['M6 9l6 6 6-6'],
    help: [
        'circle:12,12,10',
        'M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3',
        'M12 17h.01',
    ],
    key: [
        'rect:2,6,20,12,2',
        'M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10',
    ],
    full: [
        'M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3',
    ],
    magnet: [
        'M6 15V4h4v11a2 2 0 0 0 4 0V4h4v11a6 6 0 0 1-12 0z',
        'M6 8h4M14 8h4',
    ],
    rotl: ['M3 12a9 9 0 1 0 3-6.7L3 8', 'M3 3v5h5'],
    rotr: ['M21 12a9 9 0 1 1-3-6.7L21 8', 'M21 3v5h-5'],
    right: ['M9 18l6-6-6-6'],
    pin: [
        'M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z',
        'circle:12,10,2.5',
    ],
    warehouse: [
        'M22 8.35V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35Z',
        'M6 18h12M6 14h12',
        'rect:6,10,12,12,0',
    ],
};

export type IconName = keyof typeof SHAPES;

function shape(spec: string): SVGElement {
    if (spec.startsWith('circle:')) {
        const [cx, cy, r] = spec.slice(7).split(',');
        const circle = document.createElementNS(SVG_NS, 'circle');

        circle.setAttribute('cx', cx);
        circle.setAttribute('cy', cy);
        circle.setAttribute('r', r);

        return circle;
    }

    if (spec.startsWith('rect:')) {
        const [x, y, width, height, rx] = spec.slice(5).split(',');
        const rect = document.createElementNS(SVG_NS, 'rect');

        rect.setAttribute('x', x);
        rect.setAttribute('y', y);
        rect.setAttribute('width', width);
        rect.setAttribute('height', height);
        rect.setAttribute('rx', rx);

        return rect;
    }

    const path = document.createElementNS(SVG_NS, 'path');

    path.setAttribute('d', spec);

    return path;
}

/** The hidden sprite every icon points into. Mount it once inside the map root. */
export function iconSprite(): SVGSVGElement {
    const svg = document.createElementNS(SVG_NS, 'svg');
    const defs = document.createElementNS(SVG_NS, 'defs');

    svg.setAttribute('width', '0');
    svg.setAttribute('height', '0');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'wms-sprite');

    for (const [name, specs] of Object.entries(SHAPES)) {
        const symbol = document.createElementNS(SVG_NS, 'symbol');

        symbol.setAttribute('id', `wms-i-${name}`);
        symbol.setAttribute('viewBox', '0 0 24 24');
        specs.forEach((spec) => symbol.append(shape(spec)));
        defs.append(symbol);
    }

    svg.append(defs);

    return svg;
}

export function icon(name: IconName, extraClass = ''): SVGSVGElement {
    const svg = document.createElementNS(SVG_NS, 'svg');
    const use = document.createElementNS(SVG_NS, 'use');

    svg.setAttribute('class', `wms-icon${extraClass ? ` ${extraClass}` : ''}`);
    svg.setAttribute('aria-hidden', 'true');
    use.setAttribute('href', `#wms-i-${name}`);
    svg.append(use);

    return svg;
}
