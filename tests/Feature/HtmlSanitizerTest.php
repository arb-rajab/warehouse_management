<?php

use Illuminate\Support\Facades\DB;

/**
 * The legacy store prints product and classified-ad descriptions and
 * support-ticket text with a raw echo. Anyone who can write one (a seller, a
 * customer, a customer filing a ticket that staff then open) could run script in
 * the browser of whoever views it, an admin included. clean_html() is what those
 * views print now.
 */
const HOSTILE_HTML = [
    'a script' => '<p>Hi</p><script>alert(1)</script>',
    'an event handler on an image' => '<img src=x onerror=alert(1)>',
    'an event handler on a paragraph' => '<p onclick="alert(1)">t</p>',
    'a javascript link' => '<a href="javascript:alert(1)">x</a>',
    'a javascript link in odd case with a tab' => "<a href=\"  jAvA\tScRiPt:alert(1)\">x</a>",
    'a javascript link with an entity' => '<a href="java&#x09;script:alert(1)">x</a>',
    'a data: html link' => '<a href="data:text/html,<script>alert(1)</script>">x</a>',
    'an svg with onload' => '<svg onload=alert(1)><circle/></svg>',
    'an svg image' => '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+">',
    'an iframe' => '<iframe src="https://evil.example"></iframe>',
    'a style element' => '<style>@import "https://evil.example/x.css";</style><p>t</p>',
    'a style attribute with a url' => '<div style="background:url(javascript:alert(1))">t</div>',
    'a style attribute with an expression' => '<div style="width:expression(alert(1))">t</div>',
    'a form' => '<form action="javascript:alert(1)"><button>go</button></form>',
    'an object and an embed' => '<object data="x"></object><embed src="x">',
    'a meta refresh' => '<meta http-equiv="refresh" content="0;url=https://evil.example">t',
    'a base element' => '<base href="https://evil.example/"><a href="/x">t</a>',
    'mutation xss through math and style' => '<math><mtext><table><mglyph><style><!--</style><img title="--&gt;&lt;img src=1 onerror=alert(1)&gt;">',
    'a split script tag' => '<scr<script>ipt>alert(1)</scr</script>ipt>',
    'an unclosed tag' => '<img src=x onerror=alert(1)//',
    'details with ontoggle' => '<details open ontoggle=alert(1)>x</details>',
    'a video with a source' => '<video><source onerror=alert(1)></video>',
    'a conditional comment' => '<p>a</p><!--[if IE]><script>alert(1)</script><![endif]-->',
    'a template' => '<template><script>alert(1)</script></template>',
    'an input that autofocuses' => '<input autofocus onfocus=alert(1)>',
];

function assertHtmlIsInert(string $html): void
{
    expect($html)->not->toMatch('/<\s*(script|iframe|object|embed|svg|math|style|form|meta|base|link|input|button|video|audio|template|details)\b/i')
        ->and($html)->not->toMatch('/\bon[a-z]+\s*=/i')
        ->and($html)->not->toMatch('/javascript\s*:/i')
        ->and($html)->not->toMatch('/(url|expression)\s*\(/i')
        ->and($html)->not->toContain('<!--')
        ->and($html)->not->toContain('data:text')
        ->and($html)->not->toContain('data:image/svg');
}

test('hostile markup comes out inert, and stays that way when cleaned again', function (string $payload) {
    $clean = clean_html($payload);

    assertHtmlIsInert($clean);

    // A sanitiser whose output changes when it is cleaned again is one a
    // browser can re-parse differently from the tree that was checked.
    expect(clean_html($clean))->toBe($clean);
})->with(HOSTILE_HTML);

test('rich-text formatting survives', function () {
    $clean = clean_html('<h2 style="color: #333; text-align:center">Title</h2>'
        .'<p>Hello <strong>bold</strong> <em>it</em> <a href="https://example.com/p?a=1&b=2" target="_blank">link</a></p>'
        .'<ul><li>one</li></ul><table><tr><td colspan="2" style="width: 50%">cell</td></tr></table>'
        .'<img src="/uploads/all/a.png" alt="pic" width="100"><img src="data:image/png;base64,iVBORw0KGgo=" alt="inline">');

    expect($clean)->toContain('<h2 style="color: #333; text-align: center">Title</h2>')
        ->and($clean)->toContain('<strong>bold</strong>')
        ->and($clean)->toContain('href="https://example.com/p?a=1&amp;b=2"')
        ->and($clean)->toContain('target="_blank"')
        ->and($clean)->toContain('<td colspan="2" style="width: 50%">cell</td>')
        ->and($clean)->toContain('<img src="/uploads/all/a.png" alt="pic" width="100">')
        ->and($clean)->toContain('src="data:image/png;base64,iVBORw0KGgo="');
});

test('every link is marked so it cannot reach back into the page that opened it', function () {
    expect(clean_html('<a href="https://example.com" target="_blank" rel="opener">x</a>'))
        ->toBe('<a href="https://example.com" target="_blank" rel="noopener noreferrer nofollow">x</a>');
});

test('relative, mailto and tel links are kept', function () {
    $clean = clean_html('<a href="/products/x">p</a><a href="mailto:a@example.com">m</a><a href="tel:+31123456789">t</a>');

    expect($clean)->toContain('href="/products/x"')
        ->and($clean)->toContain('href="mailto:a@example.com"')
        ->and($clean)->toContain('href="tel:+31123456789"');
});

test('an element outside the allowlist is unwrapped, its text kept', function () {
    expect(clean_html('<section><marquee>kept text</marquee></section>'))->toBe('kept text');
});

test('empty input gives an empty string, and plain text is escaped', function () {
    expect(clean_html(null))->toBe('')
        ->and(clean_html("  \n "))->toBe('')
        ->and(clean_html('a < b & c'))->toBe('a &lt; b &amp; c');
});

test('the admin ticket page prints a customer\'s ticket text without its script', function () {
    $customer = createStoreUser('customer');
    $ticketId = DB::table('tickets')->insertGetId([
        'code' => 123456, 'user_id' => $customer->id, 'subject' => 'Help', 'status' => 'pending',
        'details' => "<script>alert('details-xss')</script><b>keep-details</b><img src=x onerror=alert('img-xss')>",
    ]);
    DB::table('ticket_replies')->insert(['ticket_id' => $ticketId, 'user_id' => $customer->id, 'reply' => '<a href="javascript:alert(\'link-xss\')">click</a><i>keep-reply</i><svg onload=alert(\'svg-xss\')>']);
    actingAsStoreUser(createStoreAdmin());

    // encrypt() can contain `/` or `+`, which the route cannot carry.
    do {
        $encryptedId = encrypt($ticketId);
    } while (preg_match('#[/+]#', $encryptedId));

    $this->get(route('support_ticket.admin_show', ['id' => $encryptedId]))
        ->assertOk()
        ->assertSee('keep-details', false)
        ->assertSee('keep-reply', false)
        ->assertDontSee('details-xss', false)
        ->assertDontSee("alert('img-xss')", false)
        ->assertDontSee("alert('link-xss')", false)
        ->assertDontSee('<svg onload', false);
});

test('the storefront product description prints a seller\'s description without its script', function () {
    $product = new class
    {
        public function getTranslation(string $field): string
        {
            return '<p>kept description</p><script>alert("description-xss")</script><img src=x onerror=alert("onerror-xss")>';
        }

        public function __get(string $name): mixed
        {
            return null;
        }

        public function __isset(string $name): bool
        {
            return false;
        }
    };

    $html = view('shop.frontend.product_details.description', ['detailedProduct' => $product])->render();

    expect($html)->toContain('<p>kept description</p>')
        ->and($html)->not->toContain('description-xss')
        ->and($html)->not->toContain('onerror-xss');
});

test('the support email prints ticket details without their script', function () {
    $html = view('shop.emails.support', [
        'content' => 'New ticket', 'sender' => 'A customer', 'link' => 'https://shop.example.test/ticket/1',
        'details' => '<p>kept details</p><script>alert("email-xss")</script>',
    ])->render();

    expect($html)->toContain('<p>kept details</p>')
        ->and($html)->not->toContain('email-xss');
});

test('no view prints user-written text unescaped without clean_html', function () {
    // Written by an admin, who is trusted with markup: CMS pages, blog posts,
    // payment-method notes.
    $adminAuthored = ['shop/frontend/blog/details.blade.php', 'shop/frontend/payment_select.blade.php'];
    $rawSink = '/(\{!!|@php\s+echo|<\?php\s+echo|<\?=)\s*+(?!clean_html\()[^;}]*?(->details\b|->reply\b|->description\b|getTranslation\(\'description\'\))/';
    $offenders = [];

    foreach (sourceFilesUnder(['resources/views']) as $path => $source) {
        if (! in_array(str_replace('resources/views/', '', $path), $adminAuthored, true) && preg_match($rawSink, $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
