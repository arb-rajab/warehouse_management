export type MatchSegment = { text: string; match: boolean };

/**
 * Splits `text` around every word of `query` (case-insensitive) so a result
 * row can wrap the matches in `<mark>`. Returns plain segments, never HTML, so
 * nothing in `text` is ever interpreted as markup.
 */
export function splitMatches(text: string, query: string): MatchSegment[] {
    if (text === '') {
        return [];
    }

    const words = Array.from(
        new Set(query.split(/\s+/).filter((word) => word !== '')),
    ).sort((a, b) => b.length - a.length);

    if (words.length === 0) {
        return [{ text, match: false }];
    }

    const pattern = new RegExp(
        `(${words.map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`,
        'gi',
    );
    const segments: MatchSegment[] = [];
    let last = 0;

    for (const found of text.matchAll(pattern)) {
        const start = found.index ?? 0;

        if (start > last) {
            segments.push({ text: text.slice(last, start), match: false });
        }

        segments.push({ text: found[0], match: true });
        last = start + found[0].length;
    }

    if (last < text.length) {
        segments.push({ text: text.slice(last), match: false });
    }

    return segments;
}

/** One or two capital letters for an avatar: the first letters of the first two words. */
export function initialsOf(label: string): string {
    const words = label
        .trim()
        .split(/\s+/)
        .filter((word) => word !== '');

    if (words.length === 0) {
        return '';
    }

    const letters =
        words.length === 1
            ? Array.from(words[0]).slice(0, 2)
            : [Array.from(words[0])[0], Array.from(words[1])[0]];

    return letters.join('').toLocaleUpperCase();
}
