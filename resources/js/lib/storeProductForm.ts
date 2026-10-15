/**
 * The largest amount a product form accepts: prices, discounts, taxes and
 * shipping costs are DECIMAL(20,2) columns.
 */
export const productAmountMax = '999999999999999999.99';

/** The legacy discount range format, `d-m-Y H:i:s to d-m-Y H:i:s`. */
export const discountRangePattern =
    '\\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2} to \\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2}';
