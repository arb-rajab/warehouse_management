import { DEFAULT_SETTINGS, THRESHOLDS } from './config';
import { FrameLoop } from './core/FrameLoop';
import { HashSync } from './core/HashSync';
import { Navigator } from './core/Navigator';
import { Refetcher } from './core/Refetcher';
import type { Mountable } from './core/View';
import { HttpWarehouseRepository } from './data/HttpWarehouseRepository';
import { SettingsStore } from './data/SettingsStore';
import type { Theme } from './data/SettingsStore';
import { browserStorage } from './data/Storage';
import type { StorageLike } from './data/Storage';
import type { WarehouseRepository } from './data/WarehouseRepository';
import type { HashTarget } from './domain/SlotCode';
import type { Locale, Slot } from './domain/types';
import { I18n } from './i18n/I18n';
import type { Messages } from './i18n/I18n';
import { GamepadAdapter } from './input/GamepadAdapter';
import { InputKind } from './input/InputKind';
import type { Intent, PointerCapture } from './input/Intent';
import { KeyboardMouseAdapter } from './input/KeyboardMouseAdapter';
import { TouchAdapter } from './input/TouchAdapter';
import { PlanView } from './plan2d/PlanView';
import { LazySceneView } from './scene3d/LazySceneView';
import { SceneView } from './scene3d/SceneView';
import type { SceneHost } from './scene3d/SceneView';
import { createActions } from './state/actions';
import {
    createMatchSelector,
    createProductOptionsSelector,
    selectAisleCount,
    selectHover,
    selectKeyHintKind,
    selectSelected,
    selectTarget,
} from './state/selectors';
import { createSlice } from './state/slices';
import { initialState } from './state/state';
import type { MapState } from './state/state';
import { Store } from './state/Store';
import { CameraBar } from './ui/CameraBar';
import { CaptureNotice } from './ui/CaptureNotice';
import { Crosshair } from './ui/Crosshair';
import { FloorSelector } from './ui/FloorSelector';
import { GlobalShortcuts } from './ui/GlobalShortcuts';
import { HelpDialog } from './ui/HelpDialog';
import { HintBar } from './ui/HintBar';
import { HudStack } from './ui/HudStack';
import { KeysPanel } from './ui/KeysPanel';
import { LegendDialog } from './ui/LegendDialog';
import { LoadStatus } from './ui/LoadStatus';
import { MapLayout } from './ui/MapLayout';
import { MatchedDrawer } from './ui/MatchedDrawer';
import { NameCard } from './ui/NameCard';
import { Notch } from './ui/Notch';
import { ProductPicker } from './ui/ProductPicker';
import { RackDialog } from './ui/RackDialog';
import { ResultPill } from './ui/ResultPill';
import { RootAttributes } from './ui/RootAttributes';
import { SearchBox } from './ui/SearchBox';
import { SelectionPill } from './ui/SelectionPill';
import { StateChips } from './ui/StateChips';
import { Toolbar } from './ui/Toolbar';
import { TouchControls } from './ui/TouchControls';
import { WalkBar } from './ui/WalkBar';
import { WelcomeCard } from './ui/WelcomeCard';
import { ZoomControls } from './ui/ZoomControls';
import { copyText } from './utils/clipboard';
import { debounce } from './utils/debounce';

export interface WarehouseMapOptions {
    /** The session-authenticated `rows/full`-shaped endpoint. */
    rowsUrl: string;
    /** The panel's help index, linked from the toolbar. */
    helpUrl?: string;
    locale: Locale;
    /** The `warehouseMap` messages of the current language. */
    messages: Messages;
    /** English messages, used for any key the language lacks. */
    fallbackMessages?: Messages;
    /** Changes the panel's language (it reloads the page). */
    switchLocale(locale: Locale): void;
    repository?: WarehouseRepository;
    storage?: StorageLike;
}

export interface WarehouseMapHandle {
    dispose(): void;
    /** Revalidates the warehouse now. */
    refresh(): Promise<void>;
}

const EMPTY = {} as const;
const noFonts = (): Promise<unknown> => Promise.resolve();

/** Waits (briefly) for the two fonts the 3D labels are painted with. */
async function loadFonts(): Promise<void> {
    const fonts = (document as Document & { fonts?: FontFaceSet }).fonts;

    if (!fonts) {
        return;
    }

    const loading = Promise.all([
        fonts.load('800 32px Inter'),
        fonts.load('800 30px Tajawal', 'ع'),
        fonts.ready,
    ]).catch(noFonts);

    await Promise.race([
        loading,
        new Promise((resolve) => setTimeout(resolve, 2000)),
    ]);
}

/**
 * The composition root and the only place that knows concrete classes. It
 * builds the store, the views, the input adapters, the 2D plan and the lazy 3D
 * scene, wires them through slices and intents, and returns a handle that
 * undoes everything.
 */
export function mountWarehouseMap(
    host: HTMLElement,
    options: WarehouseMapOptions,
): WarehouseMapHandle {
    const disposables: Mountable[] = [];
    const cleanups: Array<() => void> = [];
    const track = <T extends Mountable>(view: T): T => {
        view.mount();
        disposables.push(view);

        return view;
    };

    /* ---------- state ---------- */
    const settingsStore = new SettingsStore(
        options.storage ?? browserStorage(),
    );
    const i18n = new I18n(
        options.locale,
        options.messages,
        options.fallbackMessages ?? options.messages,
    );
    const systemTheme = (): Theme =>
        window.matchMedia?.('(prefers-color-scheme: light)').matches
            ? 'light'
            : 'dark';
    const savedTheme = settingsStore.loadTheme();
    const store = new Store<MapState>(
        initialState({
            locale: options.locale,
            theme: savedTheme ?? systemTheme(),
            folded: settingsStore.loadFolded(),
            settings: { ...DEFAULT_SETTINGS, ...settingsStore.loadSettings() },
            inputKind: window.matchMedia?.('(pointer: coarse)').matches
                ? 'touch'
                : 'mouse',
        }),
    );
    const actions = createActions(store);
    const matchOf = createMatchSelector();
    const productOptionsOf = createProductOptionsSelector();
    const slice = <T extends object>(select: (s: MapState) => T) =>
        createSlice(store, select);
    const t = i18n.t;
    const overlayOpen = (s: MapState) =>
        s.rack !== null || s.helpOpen || s.legendOpen || s.drawerOpen;

    /* ---------- the page ---------- */
    const layout = track(new MapLayout(host));
    const frames = new FrameLoop();
    const hud = track(new HudStack(layout.root));
    const repository =
        options.repository ?? new HttpWarehouseRepository(options.rowsUrl);
    let userChoseTheme = savedTheme !== null;
    let keysPanel: KeysPanel | null = null;
    let km: KeyboardMouseAdapter | null = null;

    const scene = new LazySceneView(
        (warehouse) =>
            new SceneView({
                canvas: layout.canvas,
                warehouse,
                frames,
                capture: km as PointerCapture,
                host: sceneHost,
                styleRoot: layout.root,
            }),
        (built) => layout.root.append(built.minimap.element),
    );

    const awayTimer = {
        id: undefined as ReturnType<typeof setTimeout> | undefined,
    };
    const sceneHost: SceneHost = {
        settings: () => store.get().settings,
        inputKind: () => store.get().inputKind,
        patch: (patch) => actions.patchScene(patch),
        openRack: (ref) => actions.openRack(ref),
        setTarget: (slot) => actions.setTarget(slot),
        setChromeAway: (away) => {
            clearTimeout(awayTimer.id);

            if (away) {
                actions.setChromeAway(true);
            } else {
                awayTimer.id = setTimeout(
                    () => actions.setChromeAway(false),
                    250,
                );
            }
        },
        toggleKeys: () => keysPanel?.toggle(),
        buzz: (ms) => {
            if (inputKind.hasTouched) {
                navigator.vibrate?.(ms);
            }
        },
        t,
    };

    const plan = track(
        new PlanView(
            layout.planStage,
            slice((s) => ({
                warehouse: s.warehouse,
                match: matchOf(s),
                floor: s.floor,
                targetId: s.targetId,
                orientation: s.planOrientation,
                active: s.view === '2d',
                locale: s.locale,
            })),
            {
                openRack: (ref) => actions.openRack(ref),
                setChromeAway: (away) => sceneHost.setChromeAway(away),
                cycleOrientation: () => {
                    const next = actions.cyclePlanOrientation();

                    actions.showHint(t(`plan_${next}`), 3200);
                },
            },
            t,
        ),
    );

    const hash = track(new HashSync(window, (target) => openTarget(target)));
    const navigator_ = new Navigator({
        view: () => store.get().view,
        setView: (view) => actions.setView(view),
        setTarget: (slot) => actions.setTarget(slot),
        closeRack: () => actions.closeRack(),
        scene,
        plan,
        fontsReady: loadFonts,
        nextFrame: (cb) => requestAnimationFrame(() => cb()),
        onSceneFailed: (error) => {
            console.error('The 3D scene could not start', error);
            actions.showHint(t('webglMissing'), 8000);
        },
        writeHash: (view) => hash.writeView(view),
    });

    function openTarget(target: HashTarget): void {
        if (target.kind === 'view') {
            navigator_.setView(target.view);

            return;
        }

        if (target.kind === 'filter') {
            actions.applyFilterLink(target.filter);

            return;
        }

        const slot = store
            .get()
            .warehouse?.slots.find(
                (s) =>
                    s.row === target.slot.row &&
                    s.col === target.slot.col &&
                    s.level === target.slot.level,
            );

        if (slot) {
            navigator_.showIn3d(slot);
        }
    }

    /* ---------- input ---------- */
    const inputKind = track(
        new InputKind(
            window,
            (kind) => actions.setInputKind(kind),
            store.get().inputKind === 'touch',
        ),
    );
    const context = {
        isSceneInteractive: () =>
            store.get().view === '3d' &&
            !overlayOpen(store.get()) &&
            scene.isBuilt,
        mode: () => store.get().scene.mode,
        nav: () => store.get().scene.nav,
        aisleCount: () => selectAisleCount(store.get()),
    };

    km = track(new KeyboardMouseAdapter(layout.canvas, document, context));
    const touchControls = track(
        new TouchControls(layout.root, layout.canvas, i18n),
    );
    const touch = track(new TouchAdapter(touchControls.elements, context));
    const gamepad = new GamepadAdapter();
    const route = (intent: Intent): void => {
        if (intent.type === 'captureChange' && intent.captured) {
            actions.showHint(t('locked'), 3200);
        }

        scene.handleIntent(intent);
    };

    cleanups.push(
        km.onIntent(
            (i) =>
                (store.get().inputKind === 'mouse' ||
                    i.type === 'captureChange') &&
                route(i),
        ),
    );
    cleanups.push(
        touch.onIntent((i) => store.get().inputKind === 'touch' && route(i)),
    );
    cleanups.push(gamepad.onIntent(route));
    cleanups.push(
        frames.onFrame(() => {
            const s = store.get();

            if (
                s.view === '3d' &&
                s.scene.mode === 'walk' &&
                s.scene.nav === 'free' &&
                !overlayOpen(s)
            ) {
                gamepad.poll();
            }
        }),
    );

    /* ---------- views ---------- */
    const search = new SearchBox(
        slice((s) => ({ query: s.query })),
        i18n,
        {
            setText: (q) => actions.setSearchText(q),
            commit: () => actions.commitSearch(),
            clear: () => actions.clearSearch(),
        },
    );
    const products = new ProductPicker(
        slice((s) => ({
            options: productOptionsOf(s),
            selected: s.filter.productIds,
            locale: s.locale,
        })),
        i18n,
        {
            toggle: (id) => actions.toggleProduct(id),
            clear: () => actions.clearProducts(),
        },
    );
    const chips = new StateChips(
        slice((s) => ({ state: s.filter.state, expiring: s.filter.expiring })),
        i18n,
        {
            setState: (state) => actions.setStateFilter(state),
            toggleExpiring: () => actions.toggleExpiring(),
        },
    );

    track(
        new RootAttributes(
            layout.root,
            slice((s) => ({
                view: s.view,
                mode: (s.scene.mode === 'fly'
                    ? s.scene.landing
                    : s.scene.mode) as 'overview' | 'walk',
                nav: s.scene.nav,
                inputKind: s.inputKind,
                folded: s.folded,
                theme: s.theme,
                captured: s.scene.captured,
                modal: overlayOpen(s),
                welcome: s.welcome,
                magnet: s.settings.magnet,
                away: s.chromeAway,
                locale: s.locale,
            })),
            i18n,
        ),
    );

    const fullscreen = (): void => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void layout.root.requestFullscreen?.();
        }
    };

    track(
        new Notch(
            layout.root,
            slice((s) => ({ folded: s.folded })),
            i18n,
            () => actions.setFolded(!store.get().folded),
        ),
    );
    track(
        new Toolbar(
            layout.root,
            slice((s) => ({
                view: s.view,
                theme: s.theme,
                searchOpen: s.searchOpen,
                locale: s.locale,
            })),
            i18n,
            {
                setView: (view) => navigator_.setView(view),
                openLegend: () => actions.setLegendOpen(true),
                openHelp: () => actions.setHelpOpen(true),
                toggleTheme: () => {
                    userChoseTheme = true;
                    actions.setTheme(
                        store.get().theme === 'dark' ? 'light' : 'dark',
                    );
                },
                switchLocale: (locale) => options.switchLocale(locale),
                toggleSearchOpen: () =>
                    actions.setSearchOpen(!store.get().searchOpen),
            },
            search,
            chips,
            products,
            options.helpUrl,
        ),
    );

    const camera = track(
        new CameraBar(
            slice((s) => ({
                rows: s.warehouse?.rows ?? [],
                aisles: selectAisleCount(s),
                byRow: matchOf(s).byRow,
                filtering: matchOf(s).filtering,
                mode: s.scene.mode,
                landing: s.scene.landing,
                activeAisle: s.scene.activeAisle,
            })),
            i18n,
            {
                goOverview: () => scene.goOverview(),
                enterAisle: (a) => scene.enterAisle(a),
            },
        ),
    );
    const result = track(
        new ResultPill(
            slice((s) => ({
                filtering: matchOf(s).filtering,
                count: matchOf(s).count,
            })),
            i18n,
            () => actions.setDrawerOpen(true),
        ),
    );
    layout.subbar.append(camera.element, result.element);

    track(
        new FloorSelector(
            layout.root,
            slice((s) => ({
                levels: s.warehouse?.levels ?? 0,
                floor: s.floor,
                filtering: matchOf(s).filtering,
                total: matchOf(s).count,
                byLevel: matchOf(s).byLevel,
            })),
            i18n,
            (floor) => actions.setFloor(floor),
        ),
    );
    track(
        new WalkBar(
            layout.root,
            slice((s) => ({
                nav: s.scene.nav,
                magnet: s.settings.magnet,
                inputKind: s.inputKind,
            })),
            i18n,
            {
                goOverview: () => scene.goOverview(),
                setNav: (nav) => scene.setNav(nav),
                toggleMagnet: () =>
                    actions.updateSettings({
                        magnet: !store.get().settings.magnet,
                    }),
                openHelp: () => actions.setHelpOpen(true),
                toggleFullscreen: fullscreen,
            },
        ),
    );
    track(
        new ZoomControls(
            layout.root,
            slice(() => EMPTY as Record<string, never>),
            i18n,
            plan,
        ),
    );
    track(
        new Crosshair(
            layout.root,
            slice((s) => ({ hot: s.scene.hoverId !== null })),
            i18n,
        ),
    );
    keysPanel = track(
        new KeysPanel(
            layout.root,
            slice((s) => ({
                kind: selectKeyHintKind(s),
                aisles: selectAisleCount(s),
                walking: s.scene.mode === 'walk',
            })),
            i18n,
        ),
    );

    const openRackAt = (slot: Slot) =>
        actions.openRack({ row: slot.row, col: slot.col, level: slot.level });

    track(
        new SelectionPill(
            hud.slot('selection'),
            slice((s) => ({
                slot: s.scene.mode === 'overview' ? selectSelected(s) : null,
                locale: s.locale,
            })),
            i18n,
            {
                openRack: openRackAt,
                walkHere: (slot) => scene.focusSlot(slot),
                show: (v) => hud.show('selection', v),
            },
        ),
    );
    track(
        new NameCard(
            hud.slot('details'),
            slice((s) => ({
                slot: selectHover(s),
                visible: s.scene.cardVisible,
                inputKind: s.inputKind,
                locale: s.locale,
            })),
            i18n,
            { openRack: openRackAt, show: (v) => hud.show('details', v) },
        ),
    );
    track(
        new CaptureNotice(
            hud.slot('capture'),
            slice((s) => ({
                visible:
                    s.view === '3d' &&
                    s.scene.mode === 'walk' &&
                    s.scene.nav === 'free' &&
                    !s.scene.captured &&
                    s.inputKind === 'mouse' &&
                    !overlayOpen(s) &&
                    !s.welcome,
            })),
            i18n,
            (v) => hud.show('capture', v),
        ),
    );
    track(
        new WelcomeCard(
            hud.slot('welcome'),
            slice((s) => ({
                eligible:
                    s.view === '3d' &&
                    s.scene.mode === 'walk' &&
                    s.scene.nav === 'free' &&
                    s.inputKind === 'mouse',
                walkedEnough: s.scene.walkedEnough,
            })),
            i18n,
            {
                visits: () => settingsStore.welcomeVisits(),
                countVisit: () => settingsStore.countWelcomeVisit(),
                setVisible: (v) => actions.setWelcome(v),
            },
            (v) => hud.show('welcome', v),
        ),
    );
    track(
        new HintBar(
            hud.slot('hint'),
            hud.slot('touchhint'),
            (slot, v) => hud.show(slot, v),
            slice((s) => ({
                hint: s.hint,
                view: s.view,
                mode: s.scene.mode,
                inputKind: s.inputKind,
                nav: s.scene.nav,
            })),
            i18n,
        ),
    );

    const copyLink = async (slot: Slot): Promise<void> => {
        const url = HashSync.slotLink(window, slot);

        if (await copyText(url)) {
            actions.showHint(t('copied'), 2200);
        } else {
            actions.showHint(url, 7000);
        }
    };

    track(
        new RackDialog(
            layout.root,
            slice((s) => ({
                rack: s.rack,
                warehouse: s.warehouse,
                match: matchOf(s),
                view: s.view,
                locale: s.locale,
            })),
            i18n,
            {
                close: () => actions.closeRack(),
                copyLink: (slot) => void copyLink(slot),
                showIn3d: (slot) => navigator_.showIn3d(slot),
                showInPlan: (slot) => navigator_.showInPlan(slot),
            },
        ),
    );
    track(
        new HelpDialog(
            layout.root,
            slice((s) => ({
                open: s.helpOpen,
                inputKind: s.inputKind,
                aisles: selectAisleCount(s),
            })),
            i18n,
            {
                close: () => actions.setHelpOpen(false),
                settings: () => store.get().settings,
                update: (patch) => actions.updateSettings(patch),
            },
        ),
    );
    track(
        new LegendDialog(
            layout.root,
            slice((s) => ({ open: s.legendOpen, view: s.view })),
            i18n,
            () => actions.setLegendOpen(false),
        ),
    );
    track(
        new MatchedDrawer(
            layout.root,
            slice((s) => ({
                open: s.drawerOpen,
                warehouse: s.warehouse,
                match: matchOf(s),
                locale: s.locale,
            })),
            i18n,
            {
                close: () => actions.setDrawerOpen(false),
                viewOnMap: (slot) => {
                    actions.setDrawerOpen(false);
                    navigator_.viewOnMap(slot);
                },
            },
        ),
    );
    track(
        new LoadStatus(
            layout.root,
            slice((s) => ({
                loaded: s.warehouse !== null,
                failed: s.loadFailed,
            })),
            i18n,
            () => void load(),
        ),
    );

    track(
        new GlobalShortcuts(
            {
                closeTopOverlay: () => actions.closeTopOverlay(),
                toggleFolded: () => actions.setFolded(!store.get().folded),
                toggleHelp: () => actions.setHelpOpen(!store.get().helpOpen),
                focusSearch: () => {
                    actions.setFolded(false);
                    search.focus();
                },
                resetPlanOrientation: () => actions.resetPlanOrientation(),
                view: () => store.get().view,
                isOverlayOpen: () => overlayOpen(store.get()),
            },
            plan,
        ),
    );

    /* ---------- reactions ---------- */
    cleanups.push(
        store.select(
            (s) => s.theme,
            (theme) => settingsStore.saveTheme(theme),
        ),
        store.select(
            (s) => s.folded,
            (folded) => settingsStore.saveFolded(folded),
        ),
        store.select(
            (s) => s.settings,
            (settings) => {
                settingsStore.saveSettings(settings);
                scene.applySettings();
            },
        ),
        store.select(
            (s) => ({ match: matchOf(s), floor: s.floor }),
            ({ match, floor }) => scene.setFilter(match, floor),
            {
                equals: (a, b) => a.match === b.match && a.floor === b.floor,
                immediate: true,
            },
        ),
        store.select(selectTarget, (slot) => scene.setTarget(slot)),
        store.select(
            (s) => s.warehouse,
            (warehouse) => warehouse && scene.setWarehouse(warehouse),
        ),
        store.select(
            (s) => s.inputKind,
            () => scene.onInputKind(),
        ),
        store.select(overlayOpen, (open) => {
            if (open) {
                km?.release();
            } else if (
                store.get().view === '3d' &&
                store.get().scene.mode === 'walk' &&
                store.get().scene.nav === 'free'
            ) {
                km?.request();
            }
        }),
        i18n.onChange(() => scene.onLocale()),
    );

    const media = window.matchMedia?.('(prefers-color-scheme: light)');
    const followSystem = () => {
        if (!userChoseTheme) {
            actions.setTheme(systemTheme());
        }
    };

    media?.addEventListener?.('change', followSystem);
    cleanups.push(() => media?.removeEventListener?.('change', followSystem));

    const resizeNow = debounce(() => {
        plan.resize();
        scene.resize();
    }, 120);

    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => resizeNow());

        observer.observe(layout.root);
        cleanups.push(() => observer.disconnect());
    } else {
        window.addEventListener('resize', resizeNow);
        cleanups.push(() => window.removeEventListener('resize', resizeNow));
    }

    cleanups.push(() => resizeNow.cancel());

    /* ---------- data ---------- */
    const refetcher = track(
        new Refetcher(
            document,
            async () => {
                const snapshot = await repository.load();

                if (snapshot.changed) {
                    actions.setWarehouse(snapshot.warehouse);
                }
            },
            () => Date.now(),
            THRESHOLDS.refetchMinIntervalMs,
        ),
    );

    async function load(): Promise<void> {
        actions.setLoadFailed(false);

        try {
            const snapshot = await repository.load();

            refetcher.markFresh();
            actions.setWarehouse(snapshot.warehouse);

            const target = hash.applyCurrent();

            // a filter link only narrows the map: the 3D scene still warms up as usual
            if (!target || target.kind === 'filter') {
                void loadFonts().then(() =>
                    setTimeout(() => scene.prepareWhenIdle(), 1500),
                );
            }
        } catch {
            actions.setLoadFailed(true);
        }
    }

    frames.mount();
    void load();

    return {
        refresh: () => refetcher.maybeRefresh(),
        dispose(): void {
            clearTimeout(awayTimer.id);
            cleanups.splice(0).forEach((fn) => fn());
            scene.dispose();
            frames.dispose();
            [...disposables].reverse().forEach((view) => view.dispose());
            disposables.length = 0;
        },
    };
}
