/** TruckResource's narrow back-office shape. */
export interface StoreTruck {
    id: number;
    name: string;
    model: string | null;
    max_pallets_number: string | null;
    description: string | null;
    license_plate: string | null;
    photos: number[];
    open_trip_id: number | null;
}

/** An existing image from the store upload library. */
export interface TruckPhoto {
    id: number;
    name: string;
    url: string | null;
}
