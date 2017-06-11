<?php

namespace jdenoc\DoxieConsumer\Tests;

use PHPUnit_Framework_TestCase as PhpUnitTestCase;
use jdenoc\DoxieConsumer\DoxieScan;

class DoxieScanTest extends PhpUnitTestCase {

    private function generate_test_doxie_scan_array(){
        return array(
            'name'=>'test.jpg',
            'size'=>0,
            'modified'=>date('Y-m-d H:i:s')
        );
    }

    /**
     * @test
     */
    public function get_doxie_scan_from_array(){
        $test_doxie_scan_array = $this->generate_test_doxie_scan_array();

        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        $this->assertEquals($test_doxie_scan_array['name'], $doxie_scan_object->name);
        $this->assertEquals($test_doxie_scan_array['size'], $doxie_scan_object->size);
        $this->assertEquals($test_doxie_scan_array['modified'], $doxie_scan_object->modified);
    }

    /**
     * @test
     */
    public function get_doxie_scan_from_json(){
        $test_doxie_scan_array = $this->generate_test_doxie_scan_array();
        $test_doxie_scan_json = json_encode($test_doxie_scan_array);

        $doxie_scan_object = new DoxieScan($test_doxie_scan_json);
        $this->assertEquals($test_doxie_scan_array['name'], $doxie_scan_object->name);
        $this->assertEquals($test_doxie_scan_array['size'], $doxie_scan_object->size);
        $this->assertEquals($test_doxie_scan_array['modified'], $doxie_scan_object->modified);
    }

    /**
     * @test
     */
    public function get_doxie_scan_as_string(){
        $test_doxie_scan_array = $this->generate_test_doxie_scan_array();
        $test_doxie_scan_json = json_encode($test_doxie_scan_array);

        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        $this->assertEquals($test_doxie_scan_json, (string) $doxie_scan_object);
    }

    /**
     * @test
     */
    public function get_doxie_scan_without_modified_component(){
        $test_doxie_scan_array = $this->generate_test_doxie_scan_array();
        $test_doxie_scan_array['modified'] = '';
        $this->assertEmpty($test_doxie_scan_array['modified']);

        $now = date('Y-m-d H:i:s');
        $doxie_scan_object = new DoxieScan($test_doxie_scan_array);
        $this->assertNotEmpty($doxie_scan_object->modified);
        $this->assertNotEquals($test_doxie_scan_array['modified'], $doxie_scan_object->modified);
        $this->assertEquals($now, $doxie_scan_object->modified);
        $this->assertEquals($test_doxie_scan_array['name'], $doxie_scan_object->name);
        $this->assertEquals($test_doxie_scan_array['size'], $doxie_scan_object->size);
    }

}