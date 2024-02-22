<?php

require_once __DIR__.'/../vendor/autoload.php';

use Jdenoc\DoxieConsumer\Commands\Consumer;
use Jdenoc\DoxieConsumer\ConsoleOutput;
use Symfony\Component\Console\Application;

$app_version = getenv("APP_VERSION", true) ?: 'dev';
$application = new Application('doxie-consumer', $app_version);

$command = new Consumer();
$command->setName('doxie-consumer:run');
$application->add($command);
$application->setDefaultCommand($command->getName(), true);

$application->run(null, new ConsoleOutput());
