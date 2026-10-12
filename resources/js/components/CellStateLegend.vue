<script setup lang="ts">
// Built entirely from CELL_STATE_COLOR/CELL_STATES/cellStateLabel — the same
// source CellSlot.vue and CellMap3D.vue use — so this legend (used by the
// Cells and CellLogs help pages) can never drift from the actual cell colors
// in the app. No colors or labels are hardcoded here.
import {
    CELL_STATE_COLOR,
    CELL_STATES,
    cellStateLabel,
} from '@/lib/cellStateColor';
</script>

<template>
    <ul class="flex flex-wrap gap-3">
        <li
            v-for="state in CELL_STATES"
            :key="state"
            class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
            :class="[
                CELL_STATE_COLOR[state].backgroundClass,
                CELL_STATE_COLOR[state].borderClass,
            ]"
        >
            <component
                :is="CELL_STATE_COLOR[state].icon"
                class="h-4 w-4 shrink-0"
            />
            {{ cellStateLabel(state) }}
        </li>
    </ul>
</template>
