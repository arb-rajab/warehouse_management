import type { ValidationSchema } from '@/lib/formValidation';
import smtpSettingsRules from '@/lib/smtpSettingsRules.json';

/**
 * UpdateSmtpSettingsRequest, in the browser. `lib/smtpSettingsRules.json` is
 * kept equal to the server's rules by SmtpSettingsClientValidationParityTest.
 * `serverOnly` lists what only the server checks: `email:rfc` (the engine has
 * no email rule); the request's `after()` checks (a new password with "remove
 * the saved password", re-entering the password for another host or username)
 * read the saved settings, so they are server-only by construction.
 */
export const smtpSettingsValidation: ValidationSchema = {
    rules: smtpSettingsRules.rules,
};
