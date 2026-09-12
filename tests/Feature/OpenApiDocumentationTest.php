<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

test('the generated OpenAPI document types the mobile dashboard stats field as an object, not a string', function () {
    $generator = app(Generator::class);
    $document = $generator(Scramble::getGeneratorConfig('default'));

    $schema = $document['paths']['/v1/dashboard']['get']['responses']['200']['content']['application/json']['schema'];
    $statsSchema = $schema['properties']['stats'];

    expect($statsSchema['type'])->toBe('object');
    expect($statsSchema['properties'])->toHaveKeys(['occupancy', 'expiring', 'activity_today', 'activity_week']);
});
