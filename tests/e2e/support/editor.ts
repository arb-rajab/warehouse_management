import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/**
 * Helpers for the specs that drive the shared Tiptap editor,
 * StoreRichTextField (.ai/rules/store-backoffice-migration.md §18), on
 * Inertia pages under the strict CSP.
 */

/** Quotes a string as a PHP single-quoted literal for seedStoreFixtures(). */
export function php(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/**
 * Records CSP violations: the browser's console reports and the
 * securitypolicyviolation events of every document that loads.
 */
export async function watchCsp(page: Page): Promise<string[]> {
    const reports: string[] = [];
    page.on('console', (message) => {
        if (/content security policy|refused to/i.test(message.text())) {
            reports.push(`${page.url()}: ${message.text()}`);
        }
    });
    await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            const log = window as unknown as { cspViolations?: string[] };
            log.cspViolations = [
                ...(log.cspViolations ?? []),
                `${event.violatedDirective} ${event.blockedURI}`,
            ];
        });
    });

    return reports;
}

export async function documentViolations(page: Page): Promise<string[]> {
    return page.evaluate(
        () =>
            (window as unknown as { cspViolations?: string[] }).cspViolations ??
            [],
    );
}

/** Loads `path` and checks it carries the enforced strict script policy. */
export async function expectStrictPolicy(
    page: Page,
    path: string,
): Promise<void> {
    const response = await page.goto(path);
    const headers = response!.headers();
    expect(headers['content-security-policy-report-only']).toBeUndefined();
    const scriptSrc = headers['content-security-policy']
        ?.split(';')
        .map((directive) => directive.trim())
        .find((directive) => directive.startsWith('script-src '));
    expect(scriptSrc).toBe("script-src 'self'");
}

/** Uploads the fixture photo to the library under `name`. */
export async function uploadPhoto(page: Page, name: string): Promise<void> {
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: `${name}.png`,
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(page.getByRole('button', { name, exact: true })).toBeVisible();
}

/**
 * Presses `key`, then waits until the editor at `content` has taken in the
 * browser's selection. Playwright can send the next key before Chromium
 * dispatches `selectionchange`, which no human typist can, so a
 * selection-moving key is followed by this.
 */
export async function press(
    page: Page,
    key: string,
    content: string,
): Promise<void> {
    await page.keyboard.press(key);
    await page.waitForFunction((selector) => {
        const element = document.querySelector(selector) as
            | (HTMLElement & {
                  editor?: {
                      state: { selection: { from: number; to: number } };
                      view: { posAtDOM(node: Node, offset: number): number };
                  };
              })
            | null;
        const selection = document.getSelection();

        if (
            !element?.editor ||
            !selection?.anchorNode ||
            !selection.focusNode
        ) {
            return false;
        }

        const { state, view } = element.editor;
        const anchor = view.posAtDOM(
            selection.anchorNode,
            selection.anchorOffset,
        );
        const head = view.posAtDOM(selection.focusNode, selection.focusOffset);

        return (
            Math.min(anchor, head) === state.selection.from &&
            Math.max(anchor, head) === state.selection.to
        );
    }, content);
}

export async function focusedToolbarItem(
    page: Page,
): Promise<string | undefined> {
    return page.evaluate(
        () =>
            (document.activeElement as HTMLElement | null)?.dataset.toolbarItem,
    );
}

/** Moves along the toolbar with the arrow key until `item` has focus. */
export async function arrowTo(
    page: Page,
    item: string,
    key = 'ArrowRight',
): Promise<void> {
    for (let step = 0; step < 30; step++) {
        if ((await focusedToolbarItem(page)) === item) {
            return;
        }

        await page.keyboard.press(key);
    }

    throw new Error(`The toolbar never reached ${item}`);
}

/** The computed text and background colours of the element at `selector`. */
export async function colorsOf(
    page: Page,
    selector: string,
): Promise<{
    foreground: [number, number, number];
    background: [number, number, number];
}> {
    return page.locator(selector).evaluate((element) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', {
            willReadFrequently: true,
        })!;
        const rgb = (color: string) => {
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return Array.from(context.getImageData(0, 0, 1, 1).data).slice(
                0,
                3,
            ) as [number, number, number];
        };
        const style = getComputedStyle(element);

        return {
            foreground: rgb(style.color),
            background: rgb(style.backgroundColor),
        };
    });
}

/** Picks `files` for the canonical upload field `id` (StoreUploadField). */
export async function pickUploads(
    page: Page,
    id: string,
    files: string[],
): Promise<void> {
    await page
        .locator(`label[for="${id}"]`)
        .locator('..')
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });

    for (const file of files) {
        await dialog.getByRole('button', { name: file, exact: true }).click();
    }

    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    await expect(dialog).toBeHidden();
}
