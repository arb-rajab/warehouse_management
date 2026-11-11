import type { ValidationSchema } from '@/lib/formValidation';
import recaptchaSettingsRules from '@/lib/recaptchaSettingsRules.json';

/**
 * UpdateRecaptchaSettingsRequest, in the browser. `lib/recaptchaSettingsRules.json`
 * is kept equal to the server's rules by RecaptchaSettingsClientValidationParityTest.
 * `serverOnly` lists the fields the browser does not check: the on/off switch is
 * a select that always posts a value (`settings`, `settings.*`). The request's
 * `after()` checks (the secret is needed for a new or changed site key, never
 * with "remove the saved secret") read the saved settings, so they stay on the
 * server.
 */
export const recaptchaSettingsValidation: ValidationSchema = {
    rules: recaptchaSettingsRules.rules,
};
