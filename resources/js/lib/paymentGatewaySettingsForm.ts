import type { ValidationSchema } from '@/lib/formValidation';
import paymentGatewaySettingsRules from '@/lib/paymentGatewaySettingsRules.json';

/**
 * UpdatePaymentGatewaySettingsRequest, in the browser.
 * `lib/paymentGatewaySettingsRules.json` is kept equal to the server's rules
 * by PaymentGatewaySettingsClientValidationParityTest. `serverOnly` lists
 * `current_password:store` (the staff member's own password, checked and
 * throttled on the server). The request's `after()` checks read the saved
 * settings (a secret required unless the saved one is kept for the same
 * account, "remove" with a new value, Stripe's test and live keys, a gateway
 * without a payment flow switched on), so they are server-only by
 * construction.
 */
export const paymentGatewaySettingsValidation: ValidationSchema = {
    rules: paymentGatewaySettingsRules.rules,
};
