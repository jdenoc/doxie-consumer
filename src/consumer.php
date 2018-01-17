<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/DoxieConsumer.php';

define("LOGGER_NAME", "doxie-consumer");
define("LOCK_NAME", "doxie-consumer");
define("OPTION_VERBOSE", "vvv");
define("OPTION_IP", "ip");
define("TIMEOUT_CONNECTION", 3);  // 3 second connection timeout
define("TIMEOUT", 300);           // 5 minute request timeout

// Setup command line options parser
$cli = new Commando\Command();
$cli->setHelp("Doxie Consumer\nConnects to a Doxie scanner and grab scans");
$cli->option(OPTION_VERBOSE)
    ->aka('verbose')
    ->describedAs("Describe what is happening, as it happens")
    ->boolean();
$cli->option(OPTION_IP)
    ->describedAs("Set IP address so we don't have to search the local network for the scanner");

// load environment variables
Dotenv::load(__DIR__.DIRECTORY_SEPARATOR.'..');

// Setup a logger
$logger = new Monolog\Logger(LOGGER_NAME.getmypid());
$logger_error_handler = new Monolog\Handler\ErrorLogHandler();
$log_formatter = new Monolog\Formatter\LineFormatter(null, 'c');    // 'c' is the date format
$log_formatter->ignoreEmptyContextAndExtra();
$logger_error_handler->setFormatter($log_formatter);
$logger_fingers_crossed_handler = new Monolog\Handler\FingersCrossedHandler($logger_error_handler);
$logger->pushHandler($logger_fingers_crossed_handler);

$cli_flags = $cli->getFlagValues();
$verbose_flag = (isset($cli_flags[OPTION_VERBOSE]) && $cli_flags[OPTION_VERBOSE] === true);
if($verbose_flag){
    // When the -vvv|--verbose flag is provided, add a handler that outputs to the terminal
    // Grabbed from http://stackoverflow.com/a/25787259
    $verbose_output_handler = new Monolog\Handler\StreamHandler('php://stdout');
    $verbose_output_handler->setFormatter($log_formatter);
    $logger->pushHandler($verbose_output_handler);
}

// setup lock file
$lock_file_store = new Symfony\Component\Lock\Store\FlockStore();
$lock_file_factory = new Symfony\Component\Lock\Factory($lock_file_store);
//// lock file will NOT have an expiration
//// lock file will NOT be automatically "released" for further use upon script termination
$lock = $lock_file_factory->createLock(LOCK_NAME, null, false);

// Setup a request client
$request_client = new Guzzle\Http\Client();
$request_client->setDefaultOption('connect_timeout', TIMEOUT_CONNECTION);
$request_client->setDefaultOption('timeout', TIMEOUT);

// setup network scanner
$network_scanner = new jdenoc\NetworkScanner\NetworkScanner();

// Initialise doxie consumer
$doxie_consumer = new jdenoc\DoxieConsumer\DoxieConsumer();
$doxie_consumer->set_request_client($request_client);
$doxie_consumer->set_logger($logger);
$doxie_consumer->set_network_scanner($network_scanner);

if(!empty($cli_flags[OPTION_IP])){
    $doxie_consumer->set_scanner_ip($cli_flags[OPTION_IP]);
}

// create lock file
if(!$lock->acquire()){
    premature_exit("Consumer is already running in another process. Terminating in favour of currently running service.", $verbose_flag, $logger, $logger::NOTICE);
}

if(!$doxie_consumer->is_available()){
    $lock->release();
    premature_exit("Scanner is not currently available", $verbose_flag, $logger, $logger::DEBUG);
}

$obtained_scans = array();
$doxie_scans = $doxie_consumer->list_scans();
foreach($doxie_scans as $doxie_scan){
    // there's a bug that downloading scans won't reset the scanner's auto-off timer.
    // You can work around it by occasionally getting a list of scans via the API while you're downloading,
    // which will reset the auto-off timer. - Doxie Support [2016-12-15]
    $doxie_consumer->list_scans();

    // The scanner should't fall asleep when calling the scan.json command, but it is.
    // In the meantime the best solution would be to fetch the thumbnail before transferring each scan,
    // even if you don't end up using the thumbnail for anything. - Doxie Support [2016-12-20]
    $doxie_consumer->get_thumbnail(
        $doxie_scan,
        sys_get_temp_dir().DIRECTORY_SEPARATOR.'thumbnail_'.$doxie_consumer->generate_download_filename($doxie_scan)
    );

    // Actually download the scan from the Doxie Scanner
    $downloaded = $doxie_consumer->get_scan(
        $doxie_scan,
        $doxie_consumer->get_download_location().DIRECTORY_SEPARATOR.$doxie_consumer->generate_download_filename($doxie_scan)
    );
    if($downloaded){
        $doxie_consumer->delete_scan($doxie_scan);
    } else {
        if(!$doxie_consumer->is_available()){
            // scanner is no longer available, break out of loop and stop consuming scans
            $logger->warning("Scanner is no longer available. Consumer terminating.");
            break;
        }
    }
}
$lock->release();

/**
 * @param string $exit_message
 * @param boolean $verbose_flag
 * @param Monolog\Logger $logger
 * @param int $log_level
 */
function premature_exit($exit_message, $verbose_flag, $logger, $log_level){
    if($verbose_flag){
        $logger->addRecord($log_level, $exit_message);
        exit;
    } else {
        die('['.date('c').'] '.$exit_message."\n");
    }
}