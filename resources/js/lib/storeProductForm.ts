import type { ValidationSchema } from '@/lib/formValidation';
import { resolveNamedRules } from '@/lib/formValidationNamedRules';
import productRules from '@/lib/storeProductRules.json';
import type {
    StoreProductFormSections,
    StoreProductSyncedField,
} from '@/types/store';

/**
 * The largest amount a product form accepts: prices, discounts, taxes and
 * shipping costs are DECIMAL(20,2) columns.
 */
export const productAmountMax = '999999999999999999.99';

/** The legacy discount range format, `d-m-Y H:i:s to d-m-Y H:i:s`. */
export const discountRangePattern =
    '\\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2} to \\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2}';

/** A rule entry of storeProductRules.json: always, or while a section shows. */
type RuleEntry =
    string[] | { when: keyof StoreProductFormSections; rules: string[] };

interface ProductRuleSpec {
    rules: Record<string, RuleEntry>;
    create: Record<string, RuleEntry>;
    edit: Record<string, RuleEntry>;
    named: Record<string, string[]>;
    readOnlyNullable: string[];
}

const specs = productRules as unknown as Record<
    'physical' | 'digital',
    ProductRuleSpec
>;

/**
 * The browser copy of a product form's FormRequest rules
 * (lib/storeProductRules.json, kept equal to the server's by
 * ProductClientValidationParityTest): the shared fields, the create or edit
 * form's own, only the sections the business settings show (the server
 * excludes the others), and `['nullable']` for a read-only Otajer field, as
 * UpdateProductRequest::readOnlyFieldRules() swaps them.
 */
function productSchema(
    spec: ProductRuleSpec,
    form: 'create' | 'edit',
    sections?: StoreProductFormSections,
    readOnlyFields: readonly string[] = [],
): ValidationSchema {
    const rules: Record<string, string[]> = {};
    for (const [field, entry] of Object.entries({
        ...spec.rules,
        ...spec[form],
    })) {
        if (Array.isArray(entry)) {
            rules[field] = entry;
        } else if (sections?.[entry.when]) {
            rules[field] = entry.rules;
        }
    }
    for (const field of readOnlyFields) {
        if (spec.readOnlyNullable.includes(field)) {
            rules[field] = ['nullable'];
        }
    }

    return { rules, named: resolveNamedRules(spec.named) };
}

/** StoreProductRequest / UpdateProductRequest, in the browser. */
export function physicalProductValidation(options: {
    form: 'create' | 'edit';
    sections: StoreProductFormSections;
    readOnlyFields?: readonly StoreProductSyncedField[];
}): ValidationSchema {
    return productSchema(
        specs.physical,
        options.form,
        options.sections,
        options.readOnlyFields,
    );
}

/** StoreDigitalProductRequest / UpdateDigitalProductRequest, in the browser. */
export function digitalProductValidation(
    form: 'create' | 'edit',
): ValidationSchema {
    return productSchema(specs.digital, form);
}
