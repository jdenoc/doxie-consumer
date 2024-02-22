<?php

namespace Jdenoc\DoxieConsumer\Tests;

use Jdenoc\DoxieConsumer\Scanner\DoxieScan;

dataset('doxie_scan_array', [
    [
        ['name' => 'test.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')],
    ],
]);

describe('get doxie scan', function() {
    test('from array', function($test_doxie_scan_array) {
        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        expect($doxie_scan_object->name)->toBe($test_doxie_scan_array['name'])
            ->and($doxie_scan_object->size)->toBe($test_doxie_scan_array['size'])
            ->and($doxie_scan_object->modified)->toBe($test_doxie_scan_array['modified']);
    })->with('doxie_scan_array');

    test('from json', function($test_doxie_scan_array) {
        $test_doxie_scan_json = json_encode($test_doxie_scan_array);

        $doxie_scan_object = new DoxieScan($test_doxie_scan_json);
        expect($doxie_scan_object->name)->toBe($test_doxie_scan_array['name'])
            ->and($doxie_scan_object->size)->toBe($test_doxie_scan_array['size'])
            ->and($doxie_scan_object->modified)->toBe($test_doxie_scan_array['modified']);
    })->with('doxie_scan_array');

    test('as string', function($test_doxie_scan_array) {
        $test_doxie_scan_json = json_encode($test_doxie_scan_array);

        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        expect((string) $doxie_scan_object)->toBe($test_doxie_scan_json);
    })->with('doxie_scan_array');

    test('without modified component', function($test_doxie_scan_array) {
        $test_doxie_scan_array['modified'] = '';
        expect($test_doxie_scan_array['modified'])->toBeEmpty();

        $now = date('Y-m-d H:i:s');
        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        expect($doxie_scan_object->modified)->not->toBeEmpty()
            ->and($doxie_scan_object->modified)->not->toBe($test_doxie_scan_array['modified'])
            ->and($doxie_scan_object->modified)->toBe($now)
            ->and($doxie_scan_object->name)->toBe($test_doxie_scan_array['name'])
            ->and($doxie_scan_object->size)->toBe($test_doxie_scan_array['size']);
    })->with('doxie_scan_array');
});
