import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    arrowTo,
    colorsOf,
    documentViolations,
    expectBold,
    expectStrictPolicy,
    focusedToolbarItem,
    php,
    press,
    uploadPhoto,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

/**
 * The newsletter form and the support ticket detail/reply page use the shared
 * Tiptap editor (.ai/rules/store-backoffice-migration.md §§18–19) on Inertia
 * pages under the strict CSP. The harness runs Laravel's array mail driver,
 * so nothing here delivers mail.
 */

const ar = JSON.parse(readFileSync('resources/js/locales/ar.json', 'utf8')) as {
    store: Record<string, Record<string, string>>;
};

/** Representative markup the retired Blade (summernote) ticket editor stored. */
const legacyDetails =
    '<p>Order <b>late</b> and <u>damaged</u></p><ul><li>Box one</li></ul><p style="text-align: center;">Thanks</p>';

const hostile =
    '<p>Visible text</p><img src="x" onerror="alert(\'stored\')"><script>alert(\'stored\')</script>' +
    '<a href="javascript:alert(\'stored\')">Bad link</a>';

function resetNewsletterRecipients(): void {
    seedStoreFixtures(String.raw`
        $user = App\Models\StoreUser::query()->firstOrNew(['email' => 'newsletter-reader@example.test']);
        $user->forceFill(['name' => 'Newsletter Reader', 'phone' => '5550000001', 'user_type' => 'customer'])->save();
        Illuminate\Support\Facades\DB::table('subscribers')->where('email', 'newsletter-subscriber@example.test')->delete();
        Illuminate\Support\Facades\DB::table('subscribers')->insert(['email' => 'newsletter-subscriber@example.test']);
    `);
}

/** Recreates one customer ticket, without replies, and returns its id. */
function resetTicket(
    code: number,
    subject: string,
    details: string,
    replies: string[] = [],
): number {
    const file = join(tmpdir(), `store-ticket-${code}.json`);
    seedStoreFixtures(String.raw`
        $customer = App\Models\StoreUser::query()->firstOrNew(['email' => 'ticket-customer@example.test']);
        $customer->forceFill(['name' => 'Ticket Customer', 'phone' => '5550000002', 'user_type' => 'customer'])->save();
        $old = App\Models\Ticket::query()->where('code', ${code})->pluck('id');
        App\Models\TicketReply::query()->whereIn('ticket_id', $old)->delete();
        App\Models\Ticket::query()->whereIn('id', $old)->delete();
        $ticket = App\Models\Ticket::forceCreate(['code' => ${code}, 'user_id' => $customer->id, 'subject' => ${php(subject)}, 'details' => ${php(details)}, 'status' => 'pending', 'viewed' => 0, 'client_viewed' => 1]);
        foreach ([${replies.map(php).join(', ')}] as $reply) {
            App\Models\TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'reply' => $reply]);
        }
        file_put_contents(${php(file)}, json_encode($ticket->id));
    `);

    return JSON.parse(readFileSync(file, 'utf8')) as number;
}

/** Runs `$expression` against the ticket and returns its JSON. */
function stored(id: number, expression: string): unknown {
    const file = join(tmpdir(), `store-ticket-state-${id}.json`);
    seedStoreFixtures(String.raw`
        $ticket = App\Models\Ticket::query()->findOrFail(${id});
        $reply = App\Models\TicketReply::query()->where('ticket_id', $ticket->id)->latest('id')->first();
        file_put_contents(${php(file)}, json_encode(${expression}));
    `);

    return JSON.parse(readFileSync(file, 'utf8'));
}

async function openTicket(page: Page, subject: string, view = 'View') {
    const visit = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/support_ticket\/\d+\/show$/.test(
                new URL(r.url()).pathname,
            ) && r.request().headers()['x-inertia'] === 'true',
    );
    await page
        .getByRole('row', { name: new RegExp(subject) })
        .getByRole('link', { name: view, exact: true })
        .click();
    expect((await visit).status()).toBe(200);
}

/** Selects the editor's current line and makes it bold, by keyboard only. */
async function boldCurrentLine(page: Page, content: string): Promise<void> {
    await press(page, 'Shift+Home', content);
    await page.keyboard.press('Alt+F10');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await arrowTo(page, 'bold');
    await page.keyboard.press('Enter');
    await expect(page.locator(content)).toBeFocused();
}

test('a newsletter is composed with formatting, keeps its draft after a refused recipient and is sent to a picked recipient', async ({
    page,
}) => {
    resetNewsletterRecipients();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/newsletter');
    await expect(
        page.getByRole('heading', { name: 'Send newsletter', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Configure SMTP before sending newsletters.'),
    ).toHaveCount(0);

    await page.locator('#subject').fill('Weekly offers');
    const content = page.locator('#content');
    await content.click();
    await page.keyboard.type('Plain line');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Fresh dates');
    await boldCurrentLine(page, '#content');
    await expect(page.locator('#content strong')).toHaveText('Fresh dates');

    // A recipient the search never offered is refused by the server, and
    // the draft survives the round trip.
    const users = page.locator('#user_emails');
    await users.evaluate(async (element) => {
        const option = new Option(
            'forged-recipient@example.test',
            'forged-recipient@example.test',
            true,
            true,
        );
        element.appendChild(option);
        element.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise((resolve) => requestAnimationFrame(resolve));
        option.remove();
    });
    await users.selectOption('forged-recipient@example.test');
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(
        page.getByText('The selected user_emails.0 is invalid.'),
    ).toBeVisible();
    await expect(page.locator('#subject')).toHaveValue('Weekly offers');
    await expect(page.locator('#content strong')).toHaveText('Fresh dates');

    // Pick a real recipient through the bounded search.
    await page.locator('#user_emails_search').fill('newsletter-reader');
    await expect(
        users.locator('option[value="newsletter-reader@example.test"]'),
    ).toHaveCount(1);
    await users.selectOption('newsletter-reader@example.test');
    await page
        .locator('#subscriber_emails_search')
        .fill('newsletter-subscriber');
    await expect(
        page.locator(
            '#subscriber_emails option[value="newsletter-subscriber@example.test"]',
        ),
    ).toHaveCount(1);
    expect(await documentViolations(page)).toEqual([]);

    const sent = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/newsletter/send' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    const response = await sent;
    expect(response.status()).toBe(302);
    const posted = response.request().postData() ?? '';
    expect(posted).toContain('newsletter-reader@example.test');
    expect(posted).not.toContain('forged-recipient');
    expect(posted).toContain('<strong>Fresh dates</strong>');
    expect(posted).not.toContain('user_emails_search');
    // The send keeps its established dashboard destination. Its Inertia
    // rendering and charts are covered by store-dashboard.spec.ts.
    expect(response.headers()['location']).toMatch(/\/temp\/admin$/);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('a ticket is answered with formatting and a library image, and its status changes', async ({
    page,
}) => {
    const id = resetTicket(700001, 'Browser ticket', legacyDetails);
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'ticket-photo');
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/support_ticket');
    await openTicket(page, 'Browser ticket');
    await expect(
        page.getByRole('heading', { name: 'Browser ticket #700001' }),
    ).toBeVisible();
    await expect(page.getByText('Status: Pending')).toBeVisible();
    expect(stored(id, '$ticket->viewed')).toBe(1);

    // The legacy Blade-editor request renders with its formatting.
    const original = page.locator('li', { hasText: 'Original request' });
    await expect(original.locator('b')).toHaveText('late');
    await expect(original.locator('u')).toHaveText('damaged');
    await expect(original.locator('ul li')).toHaveText('Box one');
    await expect(original.locator('p[style*="text-align: center"]')).toHaveText(
        'Thanks',
    );
    await expect(page.getByText('No replies yet.')).toBeVisible();

    await page.locator('#reply').click();
    await page.keyboard.type('Tracking follows');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Shipped today');
    await boldCurrentLine(page, '#reply');
    await expectBold(page, '#reply', 'Shipped today');

    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'ticket-photo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    await expect(page.locator('img[alt="ticket-photo"]')).toBeVisible();
    await page.locator('#status').selectOption('solved');
    // Still bold after the file dialog: tells a lost format apart from a
    // bolding that never happened (checked right after the keystrokes).
    await expectBold(page, '#reply', 'Shipped today');
    expect(await documentViolations(page)).toEqual([]);

    const sent = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/support_ticket/reply' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    expect((await sent).status()).toBe(302);
    await expect(page).toHaveURL(
        new RegExp(`/temp/admin/support_ticket/${id}/show$`),
    );
    await expect(page.getByText('Status: Solved')).toBeVisible();

    const reply = stored(
        id,
        "['reply' => $reply->reply, 'files' => $reply->files, 'status' => $ticket->status, 'client_viewed' => $ticket->client_viewed, 'photo' => (string) App\\Models\\Upload::query()->where('file_original_name', 'ticket-photo')->latest('id')->value('id')]",
    ) as Record<string, string | number>;
    expect(reply.reply).toBe(
        '<p>Tracking follows</p><p><strong>Shipped today</strong></p>',
    );
    expect(reply.files).toBe(reply.photo);
    expect(reply.status).toBe('solved');
    expect(reply.client_viewed).toBe(0);

    const answer = page.locator('li', { hasText: 'Shipped today' }).first();
    await expect(answer.locator('strong')).toHaveText('Shipped today');
    const attachment = answer.getByRole('link', { name: 'ticket-photo' });
    await expect(attachment).toHaveAttribute('href', /\/uploads\/all\//);
    await expect(attachment).toHaveAttribute('download', '');
    // The form starts empty again for the next reply.
    await expect(page.locator('input[name="reply"]')).toHaveValue('');
    await expect(page.locator('input[name="attachments"]')).toHaveValue('');
    expect(await documentViolations(page)).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('stored and pasted script payloads stay inert on the ticket page', async ({
    page,
}) => {
    const id = resetTicket(700002, 'Hostile ticket', hostile, [hostile]);
    const dialogs: string[] = [];
    page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message());
        await dialog.dismiss();
    });
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/support_ticket');
    await openTicket(page, 'Hostile ticket');
    await expect(page.getByText('Visible text')).toHaveCount(2);
    const thread = await page.locator('main').innerHTML();
    expect(thread).not.toMatch(/onerror|<script|javascript:/);

    await page.locator('#reply').click();
    await page.evaluate(() => {
        const data = new DataTransfer();
        data.setData(
            'text/html',
            '<p>Pasted <img src="x" onerror="alert(\'pasted\')"><img src="data:image/png;base64,iVBORw0KGgo="></p><script>alert(\'pasted\')</script><a href="javascript:alert(1)">Bad</a>',
        );
        data.setData('text/plain', 'Pasted');
        document.querySelector('#reply')!.dispatchEvent(
            new ClipboardEvent('paste', {
                clipboardData: data,
                bubbles: true,
                cancelable: true,
            }),
        );
    });
    await expect(page.locator('#reply')).toContainText('Pasted');
    expect(await page.locator('#reply').innerHTML()).not.toMatch(
        /onerror|<script|data:|javascript:/,
    );
    const sent = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/support_ticket/reply' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    expect((await sent).status()).toBe(302);
    const body = stored(id, '$reply->reply') as string;
    expect(body).toContain('Pasted');
    expect(body).not.toMatch(/onerror|<script|javascript:|data:|alert/);
    await expect(page.getByText('Pasted')).toBeVisible();
    expect(dialogs).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('both editors work in Arabic RTL with dark contrast and a keyboard-only toolbar', async ({
    page,
}) => {
    resetTicket(700003, 'RTL ticket', '<p>نص <strong>عريض</strong></p>');
    resetNewsletterRecipients();
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/support_ticket');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await openTicket(page, 'RTL ticket', ar.store.support.view);
    await expect(page.locator('#reply-label')).toHaveText(
        ar.store.support.reply,
    );
    await expect(page.locator('label[for="status"]')).toHaveText(
        ar.store.support.replyStatus,
    );
    await expect(
        page.getByText(ar.store.support.originalMessage, { exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('toolbar', { name: 'التنسيق' })).toBeVisible();

    // One tab stop into the toolbar, arrows mirrored in RTL, back to the text.
    await page.locator('#reply').focus();
    await page.keyboard.press('Shift+Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowLeft');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#reply')).toBeFocused();

    for (const selector of ['#reply', '#status']) {
        const colors = await colorsOf(page, selector);
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    expect(await documentViolations(page)).toEqual([]);

    await page.goto('/temp/admin/newsletter');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: ar.store.newsletters.title }),
    ).toBeVisible();
    await expect(page.locator('#content-label')).toHaveText(
        ar.store.newsletters.content,
    );
    await page.locator('#subject').focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#content')).toBeFocused();

    for (const selector of ['#content', '#user_emails', '#subject']) {
        const colors = await colorsOf(page, selector);
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    expect(await documentViolations(page)).toEqual([]);
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});
