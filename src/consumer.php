<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/DoxieConsumer.php';

// Setup command line options parser
$cli = new Commando\Command();
$cli->setHelp("Doxie Consumer\nConnects to a Doxie scanner and grab scans");
$cli->option('v')
    ->aka('verbose')
    ->describedAs("describe what is happening, as it happens")
    ->boolean();

// load environment variables
Dotenv::load(__DIR__.DIRECTORY_SEPARATOR.'..');

// Setup a logger
$logger = new Monolog\Logger("doxie_consumer");
$logger_error_handler = new Monolog\Handler\ErrorLogHandler();
$log_formatter = new Monolog\Formatter\LineFormatter(null, 'c');    // 'c' is the date format
$log_formatter->ignoreEmptyContextAndExtra();
$logger_error_handler->setFormatter($log_formatter);
$logger_fingers_crossed_handler = new Monolog\Handler\FingersCrossedHandler($logger_error_handler);
$logger->pushHandler($logger_fingers_crossed_handler);

$cli_flags = $cli->getFlagValues();
$verbose_flag = (isset($cli_flags['v']) && $cli_flags['v'] === true);
if($verbose_flag){
    // When the -v|--verbose flag is provided, add a handler that outputs to the terminal
    // Grabbed from http://stackoverflow.com/a/25787259
    $verbose_output_handler = new Monolog\Handler\StreamHandler('php://stdout');
    $verbose_output_handler->setFormatter($log_formatter);
    $logger->pushHandler($verbose_output_handler);
}

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
    if($verbose_flag){
        $logger->debug($exit_msg);
        exit;
    } else {
        die('['.date('c').'] '.$exit_msg."\n");
    }
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