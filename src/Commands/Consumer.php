<?php

namespace Jdenoc\DoxieConsumer\Commands;

use Jdenoc\DoxieConsumer\Scanner\DoxieScannerClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class Consumer extends Command {

    use LockableTrait;

    protected static $defaultDescription = 'Consumer process that connects to a doxie scanner and retrieve scans from device.';

    protected function configure(): void {
        $this
            ->addArgument('scanner', InputArgument::REQUIRED, 'doxie scanner hostname or IP address')
            ->addOption('use-ssl', null, InputOption::VALUE_NONE, 'should the consumer connect over https')
            ->addArgument('download-directory', InputArgument::REQUIRED, 'where we should put scans after download')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $download_dir = $input->getArgument('download-directory');
        $use_ssl = $input->getOption('use-ssl');
        $scanner_host = $input->getArgument('scanner');

        if(!$this->lock('docker-consumer')) {
            $output->info('docker-consumer is already running');
            return Command::FAILURE;
        }
        $output->debug('lock acquired');

        $protocol = $use_ssl ? 'https' : 'http';
        $scanner_endpoint = $protocol.'://'.rtrim($scanner_host, '/');
        $http_client = HttpClient::createForBaseUri($scanner_endpoint);
        $http_client->withOptions([
            'timeout' => 3,        // connection timeout in seconds
            'max_duration' => 300,  // request timeout in seconds
        ]);
        $scanner = new DoxieScannerClient($http_client, $output);

        if(!$scanner->isAvailable()) {
            $output->info("scanner not currently available");
            $this->release();
            return Command::SUCCESS;
        }

        $scans = $scanner->listAllScans();
        foreach($scans as $scan) {
            // there's a bug that downloading scans won't reset the scanner's auto-off timer.
            // You can work around it by occasionally getting a list of scans via the API while you're downloading,
            // which will reset the auto-off timer. - Doxie Support [2016-12-15]
            $scanner->listAllScans();

            // The scanner shouldn't fall asleep when calling the scan.json command, but it is.
            // In the meantime the best solution would be to fetch the thumbnail before transferring each scan,
            // even if you don't end up using the thumbnail for anything. - Doxie Support [2016-12-20]
            $scanner->getThumbnail($scan);

            // download the scan
            $scan_downloaded = $scanner->getScan($scan, $download_dir);
            if($scan_downloaded) {
                $scanner->deleteScan($scan);
            } else {
                if (!$scanner->isAvailable()) {
                    // scanner is no longer available, break out of loop and stop consuming scans
                    $output->warning("Scanner is no longer available. Terminating consumer process.");
                    break;
                }
            }
        }

        $output->debug('releasing lock');
        $this->release();
        $output->debug('lock released');
        return Command::SUCCESS;
    }

}
