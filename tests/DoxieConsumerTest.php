<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/DoxieConsumer.php';

class DoxieConsumerTest extends PHPUnit_Framework_TestCase {

    /**
     * @var string
     */
    private $_failed_logger_assert_msg = "logger should have recorded ";

    /**
     * @var Guzzle\Plugin\Mock\MockPlugin
     */
    private $_guzzle_plugin_mock;

    /**
     * @var Monolog\Logger
     */
    private $_logger;

    /**
     * @before
     */
    public function setup_guzzle_mock_plugin(){
        $this->_guzzle_plugin_mock = new Guzzle\Plugin\Mock\MockPlugin();
    }

    /**
     * @before
     */
    public function setup_dummy_logger(){
        $this->_logger = new Monolog\Logger('doxie_consumer_test:'.$this->getName());
        $logger_handler = new Monolog\Handler\TestHandler();
        $logging_filter = new Monolog\Formatter\LineFormatter();
        $logging_filter->ignoreEmptyContextAndExtra();
        $logger_handler->setFormatter($logging_filter);
        $this->_logger->pushHandler($logger_handler);
    }

    /**
     * tests is_available method is available
     * @test
     */
    public function is_available_success(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(200));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        $available = $doxie->is_available();

        $this->assertTrue($available, "should have been available\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_STATUS),
            $this->_failed_logger_assert_msg.$doxie::URI_STATUS."\n".$this->get_logger_records()
        );
    }

    /**
     * test is_available method when service is unavailable
     * @test
     */
    public function is_available_fail(){
        $request_client = $this->generate_connection_timeone_guzzle_mock();

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        $available = $doxie->is_available();

        $this->assertFalse($available, "should not have been available\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_STATUS),
            $this->_failed_logger_assert_msg.$doxie::URI_STATUS."\n".$this->get_logger_records()
        );
        $this->assertTrue(
            $this->logger_has_value('timeout'),
            $this->_failed_logger_assert_msg."timeout\n".$this->get_logger_records()
        );
    }

    /**
     * tests list_scans method when service is unavailable
     * @test
     */
    public function list_scans_unavailable(){
        $request_client = $this->generate_connection_timeone_guzzle_mock();

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scans = $doxie->list_scans();

        $this->assertEmpty($doxie_scans, "non-empty response received\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_LIST),
            $this->_failed_logger_assert_msg.$doxie::URI_LIST."\n".$this->get_logger_records()
        );
    }

    /**
     * tests list_scans method when no scans are available
     * @test
     */
    public function list_scans_none_found(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(404, null, '[]'));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scans = $doxie->list_scans();

        $this->assertEmpty($doxie_scans, "non-empty response received\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_LIST),
            $this->_failed_logger_assert_msg.$doxie::URI_LIST."\n".$this->get_logger_records()
        );
    }

    /**
     * tests list_scans method with a variety of successful responses
     * @test
     * @dataProvider set_list_scans_responses
     * @param $response
     */
    public function list_scans_success($response){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(200, null, $response));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scans = $doxie->list_scans();

        $this->assertTrue(is_array($doxie_scans), "non-array response received\n".$this->get_logger_records());
        foreach($doxie_scans as $doxie_scan){
            $this->assertInstanceOf('DoxieScan', $doxie_scan, "Array element should have been a DoxieScan object\n".$this->get_logger_records());
        }
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_LIST),
            $this->_failed_logger_assert_msg.$doxie::URI_LIST."\n".$this->get_logger_records()
        );
    }

    /**
     * provide test data for the list_scans_success unit test method
     * @return array
     */
    public function set_list_scans_responses(){
        return array(
            'no_body'=>array(''),
            'one_scan'=>array('[{"name":"/DOXIE/JPEG/IMG_XXXX.jpg", "size": 0, "modified":"2016-10-10"}]'),
            'multiple_scans'=>array('[{"name":"/DOXIE/JPEG/IMG_XXXX.jpg", "size": 0, "modified":"2016-10-10"}, {"name":"/DOXIE/JPEG/IMG_YYYY.jpg", "size": 0, "modified":"2016-10-10"}, {"name":"/DOXIE/JPEG/IMG_ZZZZ.jpg", "size": 0, "modified":"2016-10-10"}]')
        );
    }

    /**
     * tests get_scan method when service is unavailable
     * @test
     */
    public function get_scan_unavailable(){
        $request_client = $this->generate_connection_timeone_guzzle_mock();

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scan = $this->generate_generic_doxie_scan();
        $downloaded = $doxie->get_scan($doxie_scan);

        $this->assertFalse($downloaded, "somehow there was a successful response and download\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX."\n".$this->get_logger_records()
        );
    }

    /**
     * tests get_scan method when scan is not found
     * @test
     */
    public function get_scan_not_found(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(404));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scan = $this->generate_generic_doxie_scan();
        $downloaded = $doxie->get_scan($doxie_scan);

        $this->assertFalse($downloaded, "somehow there was a successful response and download file exists\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX."\n".$this->get_logger_records()
        );
    }

    /**
     * tests get_scan method during a successful response
     * @test
     */
    public function get_scan_success(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(200));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scan = $this->generate_generic_doxie_scan();
        $downloaded = $doxie->get_scan($doxie_scan);

        $this->assertTrue($downloaded, "somehow there was a successful response and download file exists\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX."\n".$this->get_logger_records()
        );
    }

    /**
     * tests delete_scans method when service is unavailable
     * @test
     */
    public function delete_scans_unavailable(){
        $request_client = $this->generate_connection_timeone_guzzle_mock();

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $deleted = $doxie->delete_scans();

        $this->assertFalse($deleted, "should return false when unable to delete scans\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_DELETE),
            $this->_failed_logger_assert_msg.$doxie::URI_DELETE."\n".$this->get_logger_records()
        );
    }

    /**
     * provide data to the delete_scans_success unit test method
     * @return array
     */
    public function set_delete_scans_data(){
        $doxie_scan1 = new DoxieScan(array('name'=>'DOXIE/JPEG/IMG_XXXX.JPG', 'size'=>0, 'modified'=>date('Y-m-d H:i:s')));
        $doxie_scan2 = new DoxieScan(array('name'=>'/DOXIE/JPEG/IMG_XXXX.JPG', 'size'=>0, 'modified'=>date('Y-m-d H:i:s')));
        $doxie_scan3 = new DoxieScan(array('name'=>'DOXIE/JPEG/IMG_YYYY.JPG', 'size'=>0, 'modified'=>date('Y-m-d H:i:s')));
        $doxie_scan4 = new DoxieScan(array('name'=>'/DOXIE/JPEG/IMG_YYYY.JPG', 'size'=>0, 'modified'=>date('Y-m-d H:i:s')));

        return array(
            'no post data'=>array(array()),
            '['.$doxie_scan1->name.']'=>array( array($doxie_scan1)),
            '['.$doxie_scan2->name.']'=>array(array($doxie_scan2)),
            '['.$doxie_scan1->name.', '.$doxie_scan3->name.']'=>array(array($doxie_scan1, $doxie_scan3)),
            '['.$doxie_scan2->name.', '.$doxie_scan3->name.']'=>array(array($doxie_scan2, $doxie_scan3)),
            '['.$doxie_scan2->name.', '.$doxie_scan4->name.']'=>array(array($doxie_scan2, $doxie_scan4)),
        );
    }

    /**
     * tests delete_scans method with a variety of scans to be deleted
     * @test
     * @dataProvider set_delete_scans_data
     * @param string $records_to_delete
     */
    public function delete_scans_success($records_to_delete){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(200));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        $deleted = $doxie->delete_scans($records_to_delete);

        $this->assertTrue($deleted, "should return true\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_DELETE),
            $this->_failed_logger_assert_msg.$doxie::URI_DELETE."\n".$this->get_logger_records()
        );
    }

    /**
     * tests delete_scan method when service is unavailable
     * @test
     */
    public function delete_scan_unavailable(){
        $request_client = $this->generate_connection_timeone_guzzle_mock();

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        $doxie_scan = $this->generate_generic_doxie_scan();
        $deleted = $doxie->delete_scan($doxie_scan);

        $this->assertFalse($deleted, "should return false\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX.$doxie_scan->name."\n".$this->get_logger_records()
        );
    }

    /**
     * tests delete_scan method when scan is unavailable for deletion
     * @test
     */
    public function delete_scan_not_found(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(403));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        $doxie_scan = $this->generate_generic_doxie_scan();
        $deleted = $doxie->delete_scan($doxie_scan);

        $this->assertFalse($deleted, "should have returned false\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX.$doxie_scan->name."\n".$this->get_logger_records()
        );
    }

    /**
     * tests delete_scan method during a successful response
     * @test
     */
    public function delete_scan_success(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(204));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->set_logger($this->_logger);

        // lets assume the is_available method returns true
        $doxie_scan = $this->generate_generic_doxie_scan();
        $deleted = $doxie->delete_scan($doxie_scan);

        $this->assertTrue($deleted, "should have returned true\n".$this->get_logger_records());
        $this->assertTrue(
            $this->logger_has_value($doxie::URI_FILE_PREFIX.$doxie_scan->name),
            $this->_failed_logger_assert_msg.$doxie::URI_FILE_PREFIX."\n".$this->get_logger_records()
        );
    }

     /**
      * tests that DoxieConsumer has received a request client dependency
      * @test
      * @expectedException InvalidArgumentException
      */
    public function request_client_not_set(){
        $doxie = new DoxieConsumer();
        $doxie->set_logger($this->_logger);
        $doxie->is_available();
    }

    /**
     * tests that DoxieConsumer has received a logger dependency
     * @test
     * @expectedException InvalidArgumentException
     */
    public function logger_not_set(){
        $this->_guzzle_plugin_mock->addResponse(new Guzzle\Http\Message\Response(200));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);

        $doxie = new DoxieConsumer();
        $doxie->set_request_client($request_client);
        $doxie->is_available();
    }

    /**
     * @return \Guzzle\Http\Client
     */
    private function generate_connection_timeone_guzzle_mock(){
        $this->_guzzle_plugin_mock->addException(new Guzzle\Http\Exception\CurlException("Connection Timeout"));
        $request_client = new Guzzle\Http\Client();
        $request_client->addSubscriber($this->_guzzle_plugin_mock);
        return $request_client;
    }

    /**
     * @return DoxieScan
     */
    private function generate_generic_doxie_scan(){
        $json_array = array('name'=>"/DOXIE/JPEG/IMG_XXXX.test", 'size'=>0, 'modified'=>date("Y-m-d H:i:s"));
        $json = json_encode($json_array);
        return new DoxieScan($json);
    }

    /**
     * Checks if logger dependency recorded text
     * @param $message
     * @return bool
     */
    private function logger_has_value($message){
        $handlers = $this->_logger->getHandlers();
        foreach($handlers as $handler){
            if($handler instanceof \Monolog\Handler\TestHandler){
                return (
                    $handler->hasInfoThatContains($message) ||
                    $handler->hasDebugThatContains($message) ||
                    $handler->hasAlertThatContains($message) ||
                    $handler->hasWarningThatContains($message) ||
                    $handler->hasErrorThatContains($message) ||
                    $handler->hasEmergencyThatContains($message) ||
                    $handler->hasCriticalThatContains($message) ||
                    $handler->hasNoticeThatContains($message)
                );
            }
        }

        return false;
    }

    /**
     * Returns all recorded logger records
     * @return string
     */
    public function get_logger_records(){
        $logger_records_string = $this->_logger->getName()." records:\n";
        $handlers = $this->_logger->getHandlers();
        foreach($handlers as $handler){
            if($handler instanceof \Monolog\Handler\TestHandler){
                $records = $handler->getRecords();
                foreach($records as $record){
                    $logger_records_string .= "\t".$record['formatted'];
                }
            }
        }

        return $logger_records_string;
    }

}