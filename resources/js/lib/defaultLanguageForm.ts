import defaultLanguageRules from '@/lib/defaultLanguageRules.json';
import type { ValidationSchema } from '@/lib/formValidation';

/**
 * UpdateDefaultLanguageRequest (the Languages page's default-language form),
 * in the browser. `lib/defaultLanguageRules.json` is kept equal to the
 * server's rules by DefaultLanguageClientValidationParityTest. The request's
 * `after()` check (the language exists and is active) reads the database, so
 * it is server-only by construction; the select lists only active languages.
 */
export const defaultLanguageValidation: ValidationSchema = {
    rules: defaultLanguageRules.rules,
};
