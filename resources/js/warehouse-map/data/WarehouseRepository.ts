import type { Warehouse } from '../domain/types';

export interface WarehouseSnapshot {
    warehouse: Warehouse;
    /** False when the server confirmed (304) that nothing changed since the last load. */
    changed: boolean;
}

/** Where the warehouse comes from; views and the composition root depend on this, not on `fetch`. */
export interface WarehouseRepository {
    load(): Promise<WarehouseSnapshot>;
}
