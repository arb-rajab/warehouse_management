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
