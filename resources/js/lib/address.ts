/** Joins the non-empty address parts (street, city, country, …) with ", ". */
export function joinAddressParts(
    parts: ReadonlyArray<string | null | undefined>,
): string {
    return parts
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '')
        .join(', ');
}

/** Google Maps search URL for a free-text address. */
export function googleMapsUrl(address: string): string {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
}
