<?php

use App\Support\ReferenceData;
use Illuminate\Support\Facades\DB;

/**
 * Both registration pages need reference data a fresh database does not have:
 * a country to pick (shop.auth.register has a text box, shop.frontend.
 * user_registration a select over the countries table) and the terms page for
 * the agreement pop-up, which used to print a business setting nothing writes.
 */
const REGISTRATION_PAGES = ['/temp/register', '/temp/users/registration'];

it('shows the real terms and conditions in the agreement pop-up', function (string $url): void {
    (new ReferenceData)->load();

    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('id="terms_conditions_text"')
        ->toContain(route('terms'))
        ->not->toContain('<textarea class="form-control" rows="20" disabled>')
        ->not->toContain('Font Definitions');
    // Words from the legacy terms text itself, not a placeholder.
    $text = html_entity_decode(strip_tags($html));
    expect(strlen(DB::table('pages')->where('type', 'terms_conditions_page')->value('content')))->toBeGreaterThan(20000)
        ->and(mb_strlen(substr($text, strpos($text, 'terms_conditions_text') ?: 0)))->toBeGreaterThan(1000);
})->with(REGISTRATION_PAGES);

it('says the terms are not available instead of showing an empty box when there is no terms page', function (string $url): void {
    $this->get($url)->assertOk()
        ->assertSee('The terms and conditions are not available yet.')
        ->assertDontSee(route('terms'));
})->with(REGISTRATION_PAGES);

it('lets the visitor pick one of the enabled countries, the priority ones first', function (): void {
    (new ReferenceData)->load();

    $html = $this->get('/temp/register')->assertOk()->getContent();

    expect($html)->toContain('<select class="form-control rounded-0" id="country" name="country" required>');
    preg_match('~<select[^>]*name="country".*?</select>~s', $html, $m);
    preg_match_all('~<option value="([^"]*)"~', $m[0], $options);
    expect($options[1])->toBe(['', 'Belgium', 'France', 'Germany', 'Netherlands', 'Austria', 'Bulgaria', 'Denmark', 'Finland',
        'Hungary', 'Iceland', 'Italy', 'Luxembourg', 'Malta', 'Norway', 'Poland', 'Portugal', 'Romania', 'Slovakia',
        'Spain', 'Sweden', 'Switzerland']);
});

it('keeps the chosen country after a failed submit', function (): void {
    (new ReferenceData)->load();

    $this->withSession(['_old_input' => ['country' => 'France']])->get('/temp/register')->assertOk()
        ->assertSee('<option value="France" selected>France</option>', false);
});

it('falls back to a text box when no country is enabled', function (): void {
    $this->get('/temp/register')->assertOk()
        ->assertSee('<input type="text" class="form-control rounded-0" id="country"', false)
        ->assertDontSee('<select class="form-control rounded-0" id="country"', false);
});

it('lists the enabled countries on the registration page that uses a select', function (): void {
    (new ReferenceData)->load();

    $html = $this->get('/temp/users/registration')->assertOk()->getContent();

    expect($html)->toContain('<option value="Netherlands"')->toContain('<option value="Belgium"');
});
