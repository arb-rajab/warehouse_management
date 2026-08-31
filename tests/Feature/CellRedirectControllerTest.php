<?php

use App\Models\Row;

test('a valid cell QR link renders a redirect page pointing at the mobile apps custom scheme', function () {
    Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);

    $response = $this->get('/cell/A/1/2');

    $response->assertOk();
    $response->assertViewIs('cell-redirect');
    $response->assertViewHas('appLink', 'warehouseapp://cell?row=A&cell=1&flat=2');
    $response->assertViewHas('label', 'A1·2');
});

test('a cell QR link for a non-existent row returns a 404', function () {
    $response = $this->get('/cell/ZZ/1/1');

    $response->assertNotFound();
});

test('a cell QR link for coordinates outside the row returns a 404', function () {
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->get('/cell/A/99/99');

    $response->assertNotFound();
});

test('a cell QR link with a non-numeric cell number returns a 404, not a 500', function () {
    Row::factory()->create(['letter' => 'A']);

    $response = $this->get('/cell/A/not-a-number/1');

    $response->assertNotFound();
});
