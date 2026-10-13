/**
 * jsdom has no canvas: give every canvas a 2D context that accepts any call, so code that
 * only draws (textures, the minimap) can run in unit tests. Returns a restore function.
 */
export function installFakeCanvas(): () => void {
    const original = HTMLCanvasElement.prototype.getContext;
    const gradient = { addColorStop: () => {} };
    const noop = () => {};
    const context = new Proxy(
        {},
        {
            get: (_, property) => {
                if (
                    property === 'createRadialGradient' ||
                    property === 'createLinearGradient'
                ) {
                    return () => gradient;
                }

                if (property === 'measureText') {
                    return (text: string) => ({ width: text.length * 8 });
                }

                return noop;
            },
            set: () => true,
        },
    );

    HTMLCanvasElement.prototype.getContext = (() => context) as never;

    return () => {
        HTMLCanvasElement.prototype.getContext = original;
    };
}
