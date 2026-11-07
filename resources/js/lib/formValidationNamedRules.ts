import { isNumeric } from '@/lib/formValidation';
import type { NamedRule } from '@/lib/formValidation';

/**
 * PHP's App\Support\ProductFormNumber::format(): an amount as typed, without
 * trailing decimal zeros ("120.50" -> "120.5", "0.00" -> "0").
 */
export function formatProductNumber(value: string): string {
    if (!value.includes('.')) {
        return value;
    }

    const trimmed = value.replace(/0+$/, '').replace(/\.$/, '');

    return trimmed === '' || trimmed === '0' ? '0' : trimmed;
}

const rangeFormat = 'd-m-Y H:i:s to d-m-Y H:i:s';
const rangePattern =
    /^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2}):(\d{2}) to (\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2}):(\d{2})$/;

/** A real calendar moment (no 31 February, no hour 24), as a UTC timestamp. */
function moment(parts: string[]): number | null {
    const [day, month, year, hour, minute, second] = parts.map(Number);
    const date = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
    date.setUTCFullYear(year);
    const same =
        date.getUTCFullYear() === year &&
        date.getUTCMonth() === month - 1 &&
        date.getUTCDate() === day &&
        date.getUTCHours() === hour &&
        date.getUTCMinutes() === minute &&
        date.getUTCSeconds() === second;

    return same ? date.getTime() : null;
}

/**
 * The copies of the server's closure rules, by the name the parity test and
 * lib/storeProductRules.json use. Each mirrors its PHP closure and its
 * message (`validation.messages.*`); keep both in step.
 */
export const namedRules: Record<string, NamedRule> = {
    /**
     * ValidatesStoreProduct::discountLimitRule(): no discount (0) is always
     * allowed; a percentage stays below 100 and a flat discount below the
     * unit price. A unit price that is not a number is left to its own rule.
     */
    discountLimit: {
        dependsOn: ['unit_price', 'discount_type'],
        validate(value, get) {
            if (!isNumeric(value) || Number(value) <= 0) {
                return null;
            }

            if (get('discount_type') === 'percent') {
                return Number(value) >= 100
                    ? { message: 'discount_percent_limit' }
                    : null;
            }

            const price = get('unit_price');

            return isNumeric(price) && Number(value) >= Number(price)
                ? {
                      message: 'discount_below_price',
                      params: { price: formatProductNumber(price) },
                  }
                : null;
        },
    },
    /**
     * ValidatesDiscountDateRange::discountDateRangeRules()'s closure: two
     * real `d-m-Y H:i:s` moments joined by " to ", the end not before the start.
     */
    discountDateRange: {
        dependsOn: [],
        validate(value) {
            const match =
                typeof value === 'string' ? value.match(rangePattern) : null;
            const start = match ? moment(match.slice(1, 7)) : null;
            const end = match ? moment(match.slice(7, 13)) : null;

            return start !== null && end !== null && end >= start
                ? null
                : { message: 'date_format', params: { format: rangeFormat } };
        },
    },
};

/** The rules a schema names, resolved; an unknown name is a programming error. */
export function resolveNamedRules(
    names: Record<string, string[]>,
): Record<string, NamedRule[]> {
    return Object.fromEntries(
        Object.entries(names).map(([field, list]) => [
            field,
            list.map((name) => {
                const rule = namedRules[name];

                if (rule === undefined) {
                    throw new Error(`Unknown client validation rule: ${name}`);
                }

                return rule;
            }),
        ]),
    );
}
