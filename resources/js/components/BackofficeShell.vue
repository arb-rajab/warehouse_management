<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import SkipToContentLink from '@/components/SkipToContentLink.vue';
import SuccessBanner from '@/components/SuccessBanner.vue';
import { t } from '@/lib/i18n';
import {
    readSidebarCollapsed,
    sidebarCollapseKey,
    writeSidebarCollapsed,
} from '@/lib/sidebarCollapse';

/**
 * The one back-office shell shared by AdminLayout (WMS) and StoreAdminLayout
 * (store control panel), .ai/rules/layouts.md. A sidebar (logo, brand name,
 * the area title, the layout's `nav` and `footer` slots) and a header (the
 * section crumb and the layout's `actions`). The layouts keep their own
 * menus, permissions and active-state logic.
 *
 * - From `lg` the sidebar folds to an icon rail; the choice is remembered
 *   per user and scope (`collapseScope`, default collapsed when
 *   `defaultCollapsed`), so the warehouse map can start folded.
 * - Below `lg` it is a drawer opened from the header: Escape, the backdrop,
 *   the close button and a visit close it, and focus returns to the menu
 *   button (except after a visit, which brings a new page).
 * - `fullBleed` hands the whole area beside the sidebar and under the header
 *   to the page (the warehouse map): no width limit, padding or banner.
 */
const props = withDefaults(
    defineProps<{
        sidebarId: string;
        area: string;
        section?: string;
        fullBleed?: boolean;
        collapseScope?: string;
        defaultCollapsed?: boolean;
    }>(),
    { collapseScope: 'page', defaultCollapsed: false },
);

const page = usePage();
const brandName = computed(() => page.props.brandName ?? '');

const drawerOpen = ref(false);
const menuButton = useTemplateRef<HTMLButtonElement>('menuButton');
const closeButton = useTemplateRef<HTMLButtonElement>('closeButton');

const collapseKey = computed(() =>
    sidebarCollapseKey(
        props.sidebarId,
        props.collapseScope,
        page.props.auth.user?.id,
    ),
);
const collapsed = ref(
    readSidebarCollapsed(collapseKey.value, props.defaultCollapsed),
);

watch(collapseKey, (key) => {
    collapsed.value = readSidebarCollapsed(key, props.defaultCollapsed);
});

function toggleCollapsed(): void {
    collapsed.value = !collapsed.value;
    writeSidebarCollapsed(collapseKey.value, collapsed.value);
}

/** Unfolds the rail, e.g. when a collapsed menu group is clicked. */
function expand(): void {
    if (collapsed.value) {
        toggleCollapsed();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeDrawer();
    }
}

async function openDrawer(): Promise<void> {
    drawerOpen.value = true;
    document.addEventListener('keydown', onKeydown);
    await nextTick();
    closeButton.value?.focus();
}

function closeDrawer(returnFocus = true): void {
    if (!drawerOpen.value) {
        return;
    }

    drawerOpen.value = false;
    document.removeEventListener('keydown', onKeydown);

    if (returnFocus) {
        menuButton.value?.focus();
    }
}

/** A nav click: the drawer closes and the visit brings the new page's focus. */
function closeAfterNavigation(): void {
    closeDrawer(false);
}

watch(
    () => page.url,
    () => closeDrawer(false),
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

defineExpose({ closeDrawer, expand, collapsed, drawerOpen });
</script>

<template>
    <div
        class="bg-page text-ink"
        :class="
            fullBleed
                ? 'flex h-dvh flex-col lg:flex-row'
                : 'min-h-screen lg:flex'
        "
    >
        <SkipToContentLink />
        <div
            v-if="drawerOpen"
            data-testid="sidebar-backdrop"
            class="fixed inset-0 z-40 bg-gray-950/40 lg:hidden"
            aria-hidden="true"
            @click="closeDrawer()"
        />
        <aside
            :id="sidebarId"
            :data-collapsed="collapsed"
            class="fixed inset-y-0 start-0 z-50 w-72 max-w-[85vw] flex-col border-e border-sidebar-line bg-sidebar text-sidebar-ink shadow-pop transition-[width] motion-reduce:transition-none lg:sticky lg:top-0 lg:z-auto lg:flex lg:h-screen lg:max-w-none lg:shrink-0 lg:shadow-none print:hidden"
            :class="[
                drawerOpen ? 'flex' : 'hidden',
                collapsed ? 'lg:w-16' : 'lg:w-64',
            ]"
        >
            <div
                class="flex items-center gap-2.5 px-4 pt-4 pb-3"
                :class="{ 'lg:justify-center lg:px-2': collapsed }"
            >
                <img
                    src="/assets/img/33logoqr.png"
                    alt=""
                    class="h-9 w-9 shrink-0 rounded-full"
                />
                <div
                    class="min-w-0 flex-1 leading-tight"
                    :class="{ 'lg:sr-only': collapsed }"
                >
                    <p
                        class="truncate font-bold text-sidebar-strong"
                        data-testid="sidebar-brand"
                    >
                        {{ brandName }}
                    </p>
                    <p
                        class="truncate text-xs text-sidebar-muted"
                        data-testid="sidebar-area"
                    >
                        {{ area }}
                    </p>
                </div>
                <button
                    ref="closeButton"
                    type="button"
                    class="cursor-pointer rounded-md p-1.5 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-strong lg:hidden"
                    :aria-label="t('nav.closeMenu')"
                    @click="closeDrawer()"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <slot
                name="nav"
                :close="closeAfterNavigation"
                :collapsed="collapsed"
                :expand="expand"
            />
            <div class="border-t border-sidebar-line">
                <slot
                    name="footer"
                    :close="closeAfterNavigation"
                    :collapsed="collapsed"
                />
            </div>
        </aside>
        <div
            class="flex min-w-0 flex-1 flex-col"
            :class="{ 'min-h-0': fullBleed }"
        >
            <header
                class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-line bg-surface px-4 sm:px-7 print:hidden"
            >
                <button
                    ref="menuButton"
                    type="button"
                    class="-ms-1.5 cursor-pointer rounded-md p-1.5 text-muted hover:bg-subtle hover:text-ink lg:hidden"
                    :aria-label="t('nav.openMenu')"
                    :aria-controls="sidebarId"
                    :aria-expanded="drawerOpen"
                    @click="openDrawer"
                >
                    <Menu class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    data-testid="sidebar-collapse"
                    class="-ms-1.5 hidden cursor-pointer rounded-md p-1.5 text-muted hover:bg-subtle hover:text-ink lg:inline-flex"
                    :aria-label="
                        t(
                            collapsed
                                ? 'nav.expandSidebar'
                                : 'nav.collapseSidebar',
                        )
                    "
                    :title="
                        t(
                            collapsed
                                ? 'nav.expandSidebar'
                                : 'nav.collapseSidebar',
                        )
                    "
                    :aria-controls="sidebarId"
                    :aria-expanded="!collapsed"
                    @click="toggleCollapsed"
                >
                    <component
                        :is="collapsed ? PanelLeftOpen : PanelLeftClose"
                        class="h-5 w-5 rtl:rotate-180"
                    />
                </button>
                <nav
                    :aria-label="t('nav.breadcrumb')"
                    class="flex min-w-0 items-center gap-1.5 text-sm text-muted"
                >
                    <span class="hidden truncate sm:inline">{{ area }}</span>
                    <ChevronRight
                        v-if="section"
                        class="hidden h-4 w-4 shrink-0 sm:inline rtl:rotate-180"
                    />
                    <span
                        v-if="section"
                        class="truncate font-semibold text-ink"
                        >{{ section }}</span
                    >
                </nav>
                <div class="ms-auto flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                </div>
            </header>
            <main
                id="main"
                :class="
                    fullBleed
                        ? 'relative min-h-0 flex-1'
                        : 'min-w-0 flex-1 overflow-x-auto px-4 py-6 sm:px-7'
                "
            >
                <div v-if="!fullBleed" class="mx-auto max-w-screen-2xl">
                    <SuccessBanner />
                    <slot />
                </div>
                <!-- The full-bleed map has no room above it for a banner. -->
                <slot v-else />
            </main>
        </div>
    </div>
</template>
