import type { ComponentPublicInstance, Ref } from 'vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The open/close + click-outside/Escape-to-close + Arrow/Home/End option
 * navigation mechanics shared by every dropdown-style filter listbox
 * (FilterMultiSelect.vue, FilterProductSelect.vue). `optionCount` is a
 * getter rather than a fixed number since FilterProductSelect's option
 * count changes as search results load in; it defaults to `() => 0` for a
 * caller that only wants the dismiss (open/click-outside/Escape) behavior
 * and has no option list to navigate — AccountMenu.vue is one such
 * degenerate caller, using just `open`/`containerRef`.
 *
 * The caller owns opening the listbox (a plain `open.value = !open.value`,
 * or something more involved like FilterProductSelect's fetch-on-open) and
 * rendering `containerRef`/`setOptionRef` onto its template; this composable
 * only owns the shared dismiss/navigate behavior.
 */
export function useDismissibleListbox(optionCount: () => number = () => 0) {
    const open = ref(false);
    const containerRef = ref<HTMLElement | null>(null);
    const optionRefs = ref<(HTMLInputElement | null)[]>([]);

    function setOptionRef(
        el: Element | ComponentPublicInstance | null,
        index: number,
    ): void {
        optionRefs.value[index] = el as HTMLInputElement | null;
    }

    function onOptionKeydown(event: KeyboardEvent, index: number): void {
        const count = optionCount();

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            optionRefs.value[(index + 1) % count]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            optionRefs.value[(index - 1 + count) % count]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            optionRefs.value[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            optionRefs.value[count - 1]?.focus();
        }
    }

    function onDocumentClick(event: MouseEvent): void {
        if (
            containerRef.value &&
            !containerRef.value.contains(event.target as Node)
        ) {
            open.value = false;
        }
    }

    function onKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            open.value = false;
        }
    }

    onMounted(() => {
        document.addEventListener('click', onDocumentClick);
        document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('click', onDocumentClick);
        document.removeEventListener('keydown', onKeydown);
    });

    return { open, containerRef, optionRefs, setOptionRef, onOptionKeydown };
}

/**
 * Caps a dropdown panel's max-width so it can't grow past the viewport edge
 * it expands toward — shared by the two panels that anchor at their
 * trigger's inline-start via plain absolute-position static placement (no
 * explicit `start-0`/`end-0`) and then size to content beyond that
 * (FilterMultiSelect.vue, FilterProductSelect.vue, both
 * `w-max max-w-xs min-w-full`). ProductSelect.vue's panel doesn't need this:
 * it's plain `w-full`, bounded by its own on-page container, so it can't
 * overflow regardless of where its trigger sits.
 *
 * The static-position anchor keeps the panel's *start* edge correctly
 * aligned with the trigger in either direction, but its *end* edge is
 * unconstrained: it grows in the inline-end direction (physically left
 * under `dir="rtl"`, right under `dir="ltr"`) up to `max-w-xs` regardless of
 * how much room is actually left on that side. For a trigger near that
 * physical edge (e.g. a `PageHeader` slot, which sits at the page's
 * physical left in RTL by `justify-between`), the panel overflows past the
 * page edge — reproduced with Playwright against the real compiled CSS: a
 * trigger 208px from a 412px-wide viewport's left edge rendered the panel
 * 68px past x=0.
 *
 * Call `recompute()` synchronously when opening the panel (before Vue
 * renders it), then bind the returned `panelMaxWidthPx` into the panel's
 * `:style`, e.g. `:style="{ maxWidth: `${panelMaxWidthPx}px` }"`. This
 * shrinks the panel instead of flipping it to the other side — acceptable
 * here since every panel's content already wraps (`break-words`/`min-w-0`).
 */
export function usePanelMaxWidth(
    containerRef: Ref<HTMLElement | null>,
    maxWidthPx = 320,
) {
    const panelMaxWidthPx = ref(maxWidthPx);
    const edgeMarginPx = 16;

    function recompute(): void {
        const el = containerRef.value;

        if (!el) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const isRtl = getComputedStyle(el).direction === 'rtl';
        const availablePx = isRtl
            ? rect.right - edgeMarginPx
            : window.innerWidth - rect.left - edgeMarginPx;

        panelMaxWidthPx.value = Math.max(Math.min(maxWidthPx, availablePx), 0);
    }

    return { panelMaxWidthPx, recompute };
}

/**
 * `isChecked`/`toggleValue` for a multi-select `string[]` model — byte-
 * identical between FilterMultiSelect.vue and FilterProductSelect.vue before
 * this extraction.
 */
export function useMultiSelectToggle(model: Ref<string[]>) {
    function isChecked(value: string): boolean {
        return model.value.includes(value);
    }

    function toggleValue(value: string): void {
        model.value = isChecked(value)
            ? model.value.filter((selected) => selected !== value)
            : [...model.value, value];
    }

    return { isChecked, toggleValue };
}
