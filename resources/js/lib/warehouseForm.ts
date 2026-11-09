import type { ValidationSchema } from '@/lib/formValidation';
import warehouseRules from '@/lib/warehouseRules.json';

/**
 * StoreWarehouseRequest / UpdateWarehouseRequest (ValidatesWarehouse), in the
 * browser. `lib/warehouseRules.json` is kept equal to the server's rules by
 * WarehouseClientValidationParityTest; the `unique` checks on the name and
 * code read the warehouses table, so only the server runs them.
 */
export const warehouseValidation: ValidationSchema = {
    rules: warehouseRules.rules,
};
