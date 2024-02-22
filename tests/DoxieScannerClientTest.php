<?php

namespace Jdenoc\DoxieConsumer\Tests;

use Jdenoc\DoxieConsumer\Scanner\DoxieScan;
use Jdenoc\DoxieConsumer\Scanner\DoxieScannerClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response as ResponseStatusCode;

dataset('single_scan_object', [
    ['scan' => new DoxieScan(['name' => '/DOXIE/JPEG/IMG_XXXX.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')])],
]);

dataset('multiple_scan_objects', [
    ['scans' => [
        new DoxieScan(['name' => '/DOXIE/JPEG/IMG_XXXX.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')]),
        new DoxieScan(['name' => '/DOXIE/JPEG/IMG_YYYY.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')]),
        new DoxieScan(['name' => '/DOXIE/JPEG/IMG_ZZZZ.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')]),
    ]],
]);

dataset('raw_multiple_scan', [
    ['scans_as_array' => [
        ['name' => '/DOXIE/JPEG/IMG_XXXX.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')],
        ['name' => '/DOXIE/JPEG/IMG_YYYY.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')],
        ['name' => '/DOXIE/JPEG/IMG_ZZZZ.jpg', 'size' => 0, 'modified' => date('Y-m-d H:i:s')],
    ]],
]);

describe('scanner status', function() {
    test('is available', function() {
        $mock_response = new JsonMockResponse(
            ["model" => "DX255","name" => "Doxie_3c7fb4","firmware" => "0.17","firmwareWiFi" => "0108","hasPassword" => false,"MAC" => "74:72:f2:3c:7f:b4","mode" => "AP","network" => "Apparent","ip" => "192.168.0.100"],
            ['http_code' => ResponseStatusCode::HTTP_OK]
        );
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $is_scanner_available = $scanner_client->isAvailable();
        expect($is_scanner_available)->toBeTrue();
    });

    test('is not available', function() {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $is_scanner_available = $scanner_client->isAvailable();
        expect($is_scanner_available)->toBeFalse();
    });
});

describe('list all scans', function() {
    test('when scanner is unavailable', function() {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listAllScans();
        expect($scans)->toBeEmpty();
    });

    test('when scanner is available and there are no scans are present', function() {
        $mock_response = new JsonMockResponse([], ['http_code' => ResponseStatusCode::HTTP_NO_CONTENT]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listAllScans();
        expect($scans)->toBeEmpty();
    });

    test('when scanner is available and scans are present', function(array $scans_as_array) {
        $mock_response = new JsonMockResponse($scans_as_array, ['http_code' => ResponseStatusCode::HTTP_OK]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listAllScans();

        expect($scans)->not->toBeEmpty()
            ->and($scans)->toBeArray();
        foreach($scans as $scan) {
            $this->assertInstanceOf(DoxieScan::class, $scan, "Array element should have been a DoxieScan object");
            expect(array_column($scans_as_array, 'name'))->toContain($scan->name);
        }
    })->with('raw_multiple_scan');
});

describe('check for new scans', function() {
    test('when scanner is unavailable', function() {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listRecentScans();
        expect($scans)->toBeEmpty();
    });

    test('when scanner is available and there are no scans present', function() {
        $mock_response = new JsonMockResponse([], ['http_code' => ResponseStatusCode::HTTP_NO_CONTENT]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listAllScans();
        expect($scans)->toBeEmpty();
    });

    test('when scanner is available and scans are present', function(array $raw_scans_data) {
        $mock_response = new JsonMockResponse($raw_scans_data, ['http_code' => ResponseStatusCode::HTTP_OK]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $scans = $scanner_client->listAllScans();

        expect($scans)->not->toBeEmpty()
            ->and($scans)->toBeArray();
        foreach($scans as $scan) {
            $this->assertInstanceOf(DoxieScan::class, $scan, "Array element should have been a DoxieScan object");
            expect(array_column($raw_scans_data, 'name'))->toContain($scan->name);
        }
    })->with('raw_multiple_scan');
});

describe('get scan', function() {
    test('when scanner is unavailable', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_downloaded = $scanner_client->getScan($scan_data, sys_get_temp_dir());
        expect($has_scan_been_downloaded)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan not found', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_NOT_FOUND]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_downloaded = $scanner_client->getScan($scan_data, sys_get_temp_dir());
        expect($has_scan_been_downloaded)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan found and downloaded', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_OK]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_downloaded = $scanner_client->getScan($scan_data, sys_get_temp_dir());
        expect($has_scan_been_downloaded)->toBeTrue();
    })->with('single_scan_object');
});

describe('get thumbnail', function() {
    test('when scanner is unavailable', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_thumbnail_been_downloaded = $scanner_client->getThumbnail($scan_data);
        expect($has_thumbnail_been_downloaded)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan not found', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_NOT_FOUND]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_thumbnail_been_downloaded = $scanner_client->getThumbnail($scan_data);
        expect($has_thumbnail_been_downloaded)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan found and downloaded', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_OK]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_thumbnail_been_downloaded = $scanner_client->getThumbnail($scan_data);
        expect($has_thumbnail_been_downloaded)->toBeTrue();
    })->with('single_scan_object');
});

describe('delete scan', function() {
    test('when scanner is unavailable', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_deleted = $scanner_client->deleteScan($scan_data);
        expect($has_scan_been_deleted)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan not found', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_NOT_FOUND]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_deleted = $scanner_client->deleteScan($scan_data);
        expect($has_scan_been_deleted)->toBeFalse();
    })->with('single_scan_object');

    test('when scanner is available and scan found', function(DoxieScan $scan_data) {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_NO_CONTENT]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $has_scan_been_deleted = $scanner_client->deleteScan($scan_data);
        expect($has_scan_been_deleted)->toBeTrue();
    })->with('single_scan_object');
});

describe('delete multiple scans', function() {
    test('when scanner is unavailable', function() {
        $mock_response = new MockResponse('', ['error' => "forcing scanner to be unavailable"]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $have_scans_been_deleted = $scanner_client->deleteScans([]);
        expect($have_scans_been_deleted)->toBeFalse();
    });

    test('when scanner is available and request succeeds', function() {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_FORBIDDEN]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $have_scans_been_deleted = $scanner_client->deleteScans([]);
        expect($have_scans_been_deleted)->toBeFalse();
    })->with('multiple_scan_objects');

    test('when scanner is available and request fails', function() {
        $mock_response = new MockResponse('', ['http_code' => ResponseStatusCode::HTTP_NO_CONTENT]);
        $mock_http_client = new MockHttpClient($mock_response, 'http://dummy-doxie.test');

        $scanner_client = new DoxieScannerClient($mock_http_client);
        $have_scans_been_deleted = $scanner_client->deleteScans([]);
        expect($have_scans_been_deleted)->toBeTrue();
    })->with('multiple_scan_objects');
});
