/**
 * The physical warehouse, in metres. These match the real racks and the mobile
 * app's 3D model: do not "tidy" them.
 */
export interface Layout {
    /** One bay (a column) along the aisle. */
    colSpacing: number;
    rackWidth: number;
    rackDepth: number;
    levelHeight: number;
    /** Width of the walking lane between two racks. */
    aisleWidth: number;
    /** Distance between the centre lines of two neighbouring aisles. */
    aisleGap: number;
    palletH: number;
    cargoH: number;
    beamT: number;
    beamBase: number;
    upright: number;
    topClear: number;
    /** Eye height when walking at floor level. */
    eye: number;
    fovWalk: number;
    fovOver: number;
    ceilClear: number;
    /** Free floor in front of the entrance. */
    apron: number;
}

export const LAYOUT: Layout = {
    colSpacing: 1.6,
    rackWidth: 1.25,
    rackDepth: 0.95,
    levelHeight: 1.15,
    aisleWidth: 8,
    aisleGap: 10.4,
    palletH: 0.12,
    cargoH: 0.7,
    beamT: 0.1,
    beamBase: 0.18,
    upright: 0.06,
    topClear: 0.2,
    eye: 1.7,
    fovWalk: 62,
    fovOver: 50,
    ceilClear: 3.2,
    apron: 10,
};
