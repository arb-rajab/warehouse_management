import deliverySettingsRules from '@/lib/deliverySettingsRules.json';
import type { ValidationSchema } from '@/lib/formValidation';

/**
 * UpdateDeliverySettingsRequest (the Order configuration page's delivery
 * form), in the browser. `lib/deliverySettingsRules.json` is kept equal to the
 * server's rules by DeliverySettingsClientValidationParityTest. `serverOnly`
 * lists what only the server checks: the timezone must be one of PHP's
 * identifiers (`Rule::in(DateTimeZone::listIdentifiers())`); the form's select
 * offers exactly that list, so the browser cannot send another.
 */
export const deliverySettingsValidation: ValidationSchema = {
    rules: deliverySettingsRules.rules,
};
