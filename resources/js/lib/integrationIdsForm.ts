import type { ValidationSchema } from '@/lib/formValidation';
import integrationIdsRules from '@/lib/integrationIdsRules.json';

export type IntegrationIdsSection = keyof typeof integrationIdsRules.sections;

/**
 * UpdateIntegrationIdsRequest for one page (Analytics, Facebook chat, Facebook
 * comments), in the browser. `lib/integrationIdsRules.json` is kept equal to the
 * server's rules by IntegrationIdsClientValidationParityTest. `serverOnly` lists
 * the fields the browser does not check: the on/off switches are selects that
 * always post a value (`settings` and `settings.*`).
 */
export function integrationIdsValidation(
    section: IntegrationIdsSection,
): ValidationSchema {
    return { rules: integrationIdsRules.sections[section] };
}
