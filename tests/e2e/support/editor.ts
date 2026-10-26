import { readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import type { Editor } from '@tiptap/core';
import { seedStoreFixtures } from './storeFixtures';

/**
 * Helpers for the specs that drive the shared Tiptap editor,
 * StoreRichTextField (.ai/archive/store-backoffice/changes-20261013.md §18), on
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
    await uploadFixture(page, name, {
        extension: 'png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
}

/** Uploads one named fixture, replacing an earlier repetition's copy. */
export async function uploadFixture(
    page: Page,
    name: string,
    file: { extension: string; mimeType: string; buffer: Buffer },
): Promise<void> {
    // Repetitions reuse the database; keep this named fixture unambiguous.
    seedStoreFixtures(String.raw`
        $uploads = app(App\Services\MultipartUploadService::class);
        foreach (App\Models\Upload::where('file_original_name', ${php(name)})->get() as $upload) {
            $uploads->delete($upload);
        }
    `);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: `${name}.${file.extension}`,
        mimeType: file.mimeType,
        buffer: file.buffer,
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(page.getByRole('button', { name, exact: true })).toBeVisible();
}

/**
 * Waits for focus and, when selecting text, the typed editor content before
 * pressing `key`. Synchronizes the click's selection before sending the key,
 * so a pending pointer update cannot restore the old caret afterward.
 * Then waits for both the browser and Tiptap to select the
 * expected text. Matching positions alone can also describe an empty caret;
 * moving to the toolbar then would toggle a stored mark instead of formatting.
 */
export async function press(
    page: Page,
    key: string,
    content: string,
    selectedText?: string,
): Promise<void> {
    const field = page.locator(content);
    await expect(field).toBeFocused();

    if (selectedText !== undefined) {
        await expect
            .poll(() =>
                field.evaluate(
                    (element) =>
                        (
                            element as HTMLElement & { editor?: Editor }
                        ).editor?.getText() ?? '',
                ),
            )
            .toContain(selectedText);
    }

    const selectionState = () =>
        field.evaluate((element) => {
            const current = (element as HTMLElement & { editor?: Editor })
                .editor;
            const selection = document.getSelection();

            if (
                !current ||
                !selection?.anchorNode ||
                !selection.focusNode ||
                !element.contains(selection.anchorNode) ||
                !element.contains(selection.focusNode)
            ) {
                return null;
            }

            const { state, view } = current;
            const { from, to } = state.selection;
            const anchor = view.posAtDOM(
                selection.anchorNode,
                selection.anchorOffset,
            );
            const head = view.posAtDOM(
                selection.focusNode,
                selection.focusOffset,
            );

            return {
                focused:
                    document.activeElement === element && current.isFocused,
                synchronized:
                    Math.min(anchor, head) === from &&
                    Math.max(anchor, head) === to,
                browserText: selection.toString(),
                editorText: state.doc.textBetween(from, to, '\n'),
            };
        });

    await expect.poll(selectionState).toMatchObject({
        focused: true,
        synchronized: true,
    });
    await page.keyboard.press(key);
    await expect.poll(selectionState).toMatchObject({
        focused: true,
        synchronized: true,
        ...(selectedText === undefined
            ? {}
            : { browserText: selectedText, editorText: selectedText }),
    });
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

/**
 * Asserts the editor's bold text. Bolding by keyboard has failed
 * intermittently in CI with no record of why, so a failure names the
 * editor's HTML and the document selection at that point.
 */
export async function expectBold(
    page: Page,
    editor: string,
    text: string,
): Promise<void> {
    try {
        await expect(page.locator(`${editor} strong`)).toHaveText(text);
    } catch (error) {
        const html = await page.locator(editor).innerHTML();
        const selection = await page.evaluate(() =>
            String(document.getSelection()),
        );

        throw new Error(
            `${(error as Error).message}\neditor HTML: ${html}\nselection: ${JSON.stringify(selection)}`,
        );
    }
}
