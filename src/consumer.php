<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/DoxieConsumer.php';

// load environment variables
Dotenv::load(__DIR__.'/..');

// Setup a logger
$logger = new Monolog\Logger("doxie_consumer");
$logger_error_handler = new Monolog\Handler\ErrorLogHandler();
$logging_filter = new Monolog\Formatter\LineFormatter();
$logging_filter->ignoreEmptyContextAndExtra();
$logger_error_handler->setFormatter($logging_filter);
$logger_fingers_crossed_handler = new Monolog\Handler\FingersCrossedHandler($logger_error_handler);
$logger->pushHandler($logger_fingers_crossed_handler);

// Setup a request client
$request_client = new Guzzle\Http\Client();
$request_client->setDefaultOption('timeout', 10);
$request_client->setDefaultOption('connect_timeout', 5);

// Initialise doxie consumer
$doxie_consumer = new DoxieConsumer();
$doxie_consumer->set_request_client($request_client);
$doxie_consumer->set_logger($logger);

if(!$doxie_consumer->is_available()){
    $exit_msg = "scanner is not currently available";
    $logger->info($exit_msg);
    die($exit_msg);
}

$obtained_scans = array();
$doxie_scans = $doxie_consumer->list_scans();
foreach($doxie_scans as $doxie_scan){
    $downloaded = $doxie_consumer->get_scan($doxie_scan);
    if($downloaded){
        $obtained_scans[] = $doxie_scan;
    }
}

$doxie_consumer->delete_scans($obtained_scans);