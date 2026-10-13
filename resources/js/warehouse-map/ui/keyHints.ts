import type { KeyHintKind } from '../state/selectors';
import { kbd } from '../utils/dom';

export interface KeyRow {
    keys: string[];
    /** Translation key of the description. */
    description: string;
}

const row = (keys: string[], description: string): KeyRow => ({
    keys,
    description,
});

/** `1-9` normally, `1-0` once there are more than nine aisles. */
function aisleKeys(aisles: number): string {
    return aisles > 9 ? '1–0' : '1–9';
}

/**
 * The key hints for each situation. `compact` feeds the always-there panel at
 * the corner of the screen, the full list feeds the help dialog. Descriptions
 * and non-letter keys are translation keys (prefixed `k:`), so they follow the language.
 */
export function keyRows(
    kind: KeyHintKind,
    aisles: number,
    compact: boolean,
): KeyRow[] {
    const WASD = ['W', 'A', 'S', 'D'];

    switch (kind) {
        case 'plan':
            return [
                row(['k:kDrag'], 'kPan'),
                row(['k:kWheel'], 'kZoom'),
                row(['k:kClick'], 'kRack'),
                row(['k:kDouble'], 'kFit'),
                row(['0'], 'kFit'),
                row(['R'], 'kRotate'),
                row(['/'], 'kSearch'),
                row(['T'], 'kFold'),
                row(['?'], 'kAll'),
            ];
        case 'overview':
            return [
                row(['k:kDrag'], 'kOrbit'),
                row(['k:kRDrag'], 'kPan'),
                row(WASD, 'kPan'),
                row(['k:kWheel'], 'kZoom'),
                row(['k:kClick'], 'kInspect'),
                row([aisleKeys(aisles)], 'kAisleN'),
                row(['T'], 'kFold'),
                row(['?'], 'kAll'),
            ];
        case 'free':
            return compact
                ? [
                      row(WASD, 'kMove'),
                      row(['k:kMouse'], 'kLookMouse'),
                      row(['Shift'], 'kSprint'),
                      row(['Space', 'Z'], 'kUpDown'),
                      row(['k:kClick'], 'kInspect'),
                      row(['Esc'], 'kRelease'),
                      row(['G'], 'kToGuided'),
                      row(['O'], 'kOverview'),
                      row(['T'], 'kFold'),
                      row(['?'], 'kAll'),
                  ]
                : [
                      row(WASD, 'kMove'),
                      row(['k:kMouse'], 'kLookMouse'),
                      row(['Shift'], 'kSprint'),
                      row(['Space'], 'kUp'),
                      row(['Z'], 'kDown'),
                      row(['k:kRMB'], 'kZoomHold'),
                      row(['k:kWheel'], 'kLens'),
                      row(['k:kClick', 'F'], 'kInspect'),
                      row(['Esc'], 'kRelease'),
                      row(['G'], 'kToGuided'),
                      row(['O'], 'kOverview'),
                      row([aisleKeys(aisles)], 'kAisleN'),
                      row(['M'], 'kMap'),
                  ];
        case 'guided':
            return compact
                ? [
                      row(['W', 'S'], 'gStep'),
                      row(['A', 'D'], 'gStrafe'),
                      row(['Q', 'E'], 'gTurn'),
                      row(['Space', 'Z'], 'gLevel'),
                      row(['k:kClick'], 'kInspect'),
                      row(['G'], 'kToFree'),
                      row(['O'], 'kOverview'),
                      row(['T'], 'kFold'),
                      row(['?'], 'kAll'),
                  ]
                : [
                      row(['W', 'S'], 'gStep'),
                      row(['A', 'D'], 'gStrafe'),
                      row(['Q', 'E'], 'gTurn'),
                      row(['Space', 'Z'], 'gLevel'),
                      row(['Shift', 'W'], 'gBay4'),
                      row(['k:kWheel'], 'gWheel'),
                      row(['k:kDrag'], 'gPeek'),
                      row(['k:kClick', 'F'], 'kInspect'),
                      row(['G'], 'kToFree'),
                      row(['O'], 'kOverview'),
                      row([aisleKeys(aisles)], 'kAisleN'),
                  ];
    }
}

/** Renders the keys of a row as `<kbd>` elements, translating the `k:` ones. */
export function keyCaps(
    keys: string[],
    t: (key: string) => string,
): HTMLElement[] {
    return kbd(
        ...keys.map((key) => (key.startsWith('k:') ? t(key.slice(2)) : key)),
    );
}
