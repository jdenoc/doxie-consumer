<?php

require_once __DIR__.'/../vendor/autoload.php';

use eiriksm\GitInfo\GitInfo;
use Jdenoc\DoxieConsumer\Commands\Consumer;
use Jdenoc\DoxieConsumer\ConsoleOutput;
use Symfony\Component\Console\Application;

$info = new GitInfo();
$app_version = $info->getVersion();
$application = new Application('doxie-consumer', $app_version);

$command = new Consumer();
$command->setName('doxie-consumer:run');
$application->add($command);
$application->setDefaultCommand($command->getName(), true);

$application->run(null, new ConsoleOutput());
