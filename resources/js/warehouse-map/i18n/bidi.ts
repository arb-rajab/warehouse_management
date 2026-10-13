const LRI = '⁦';
const PDI = '⁩';

/** Keeps a code or an aisle name in its own direction inside an Arabic sentence (plain text). */
export const isolateLtr = (text: string | number): string =>
    `${LRI}${text}${PDI}`;
