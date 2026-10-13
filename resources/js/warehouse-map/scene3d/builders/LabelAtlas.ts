import * as THREE from 'three';
import { productHue } from '../../domain/expiry';

export interface ProductLabel {
    id: number;
    name: string;
    arName: string;
    boxes: number | null;
}

function canvasOf(
    width: number,
    height: number,
): [HTMLCanvasElement, CanvasRenderingContext2D] {
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    return [canvas, canvas.getContext('2d') as CanvasRenderingContext2D];
}

function finish(
    canvas: HTMLCanvasElement,
    anisotropy: number,
): THREE.CanvasTexture {
    const texture = new THREE.CanvasTexture(canvas);

    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = anisotropy;

    return texture;
}

/** Shrinks the font until the text fits `maxWidth`. */
function fitFont(
    g: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
    size: number,
    face: string,
): void {
    let current = size;

    while (current > 12) {
        g.font = `800 ${current}px ${face}, sans-serif`;

        if (g.measureText(text).width <= maxWidth) {
            return;
        }

        current -= 2;
    }
}

/**
 * Draws every texture the scene needs from text: product labels, aisle signs,
 * the floor's bay numbers (one atlas for all of them) and the out-of-service
 * mark. Only draws; the builders decide where the textures go.
 */
export class LabelAtlas {
    /** Text planes and the floor sign need fonts that are already loaded. */
    text(
        text: string,
        width: number,
        height: number,
        background: string | null,
        foreground: string,
        size: number,
    ): THREE.CanvasTexture {
        const [canvas, g] = canvasOf(width, height);

        if (background) {
            g.fillStyle = background;
            g.beginPath();

            if (g.roundRect) {
                g.roundRect(0, 0, width, height, height * 0.22);
            } else {
                g.rect(0, 0, width, height);
            }

            g.fill();
        }

        g.fillStyle = foreground;
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.font = `800 ${size}px Inter, Tajawal, sans-serif`;
        g.fillText(text, width / 2, height / 2 + 2);

        return finish(canvas, 4);
    }

    /** A product label for the aisle-facing side of a pallet. */
    product(label: ProductLabel): THREE.CanvasTexture {
        const [canvas, g] = canvasOf(400, 170);

        g.fillStyle = '#F8FAFC';
        g.fillRect(0, 0, 400, 170);
        g.fillStyle = `hsl(${productHue(label.id)} 60% 42%)`;
        g.fillRect(0, 0, 400, 34);
        g.fillStyle = '#fff';
        g.font = '800 22px Inter, sans-serif';
        g.textAlign = 'left';
        g.textBaseline = 'middle';
        g.fillText(`#${String(label.id).padStart(3, '0')}`, 14, 18);
        g.textAlign = 'right';
        g.fillText(label.boxes ? `${label.boxes} ×` : '', 386, 18);
        g.fillStyle = '#0F172A';
        g.textAlign = 'center';
        fitFont(g, label.name, 372, 31, 'Inter');
        g.fillText(label.name, 200, 82);
        g.fillStyle = '#475569';
        fitFont(g, label.arName, 372, 30, 'Tajawal');
        g.fillText(label.arName, 200, 132);

        return finish(canvas, 8);
    }

    /** The red cross over an out-of-service slot. */
    inactive(): THREE.CanvasTexture {
        const [canvas, g] = canvasOf(128, 128);

        g.fillStyle = 'rgba(225,29,72,.22)';
        g.fillRect(0, 0, 128, 128);
        g.strokeStyle = '#E11D48';
        g.lineWidth = 8;
        g.strokeRect(4, 4, 120, 120);
        g.beginPath();
        g.moveTo(14, 14);
        g.lineTo(114, 114);
        g.moveTo(114, 14);
        g.lineTo(14, 114);
        g.stroke();

        return finish(canvas, 1);
    }

    /** The tiling floor texture. */
    grid(background: string, line: string): THREE.CanvasTexture {
        const [canvas, g] = canvasOf(128, 128);

        g.fillStyle = background;
        g.fillRect(0, 0, 128, 128);
        g.strokeStyle = line;
        g.lineWidth = 2;
        g.strokeRect(0, 0, 128, 128);

        const texture = finish(canvas, 8);

        texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
        texture.repeat.set(160, 160);

        return texture;
    }

    /** The soft blue glow under the racks. */
    glow(): THREE.CanvasTexture {
        const [canvas, g] = canvasOf(256, 256);
        const gradient = g.createRadialGradient(128, 128, 10, 128, 128, 128);

        gradient.addColorStop(0, 'rgba(30,58,138,.55)');
        gradient.addColorStop(1, 'rgba(30,58,138,0)');
        g.fillStyle = gradient;
        g.fillRect(0, 0, 256, 256);

        return finish(canvas, 1);
    }

    /**
     * All the floor's bay numbers on ONE texture: a mesh and a texture per
     * slot (880 of each at 20 x 44) made the scene slow to build.
     */
    bayNumbers(cols: number): {
        texture: THREE.CanvasTexture;
        perRow: number;
        rows: number;
    } {
        const cellW = 128;
        const cellH = 64;
        const perRow = 8;
        const rows = Math.max(1, Math.ceil(cols / perRow));
        const [canvas, g] = canvasOf(perRow * cellW, rows * cellH);

        g.fillStyle = 'rgba(226,232,240,.9)';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.font = '800 46px Inter, sans-serif';

        for (let c = 1; c <= cols; c++) {
            g.fillText(
                String(c),
                (((c - 1) % perRow) + 0.5) * cellW,
                (Math.floor((c - 1) / perRow) + 0.5) * cellH + 2,
            );
        }

        return { texture: finish(canvas, 8), perRow, rows };
    }
}
