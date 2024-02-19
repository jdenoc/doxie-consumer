<?php

namespace Jdenoc\DoxieConsumer;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleOutput extends \Symfony\Component\Console\Output\ConsoleOutput {

    public function __construct(int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null) {
        parent::__construct($verbosity, $decorated, $formatter);

        $debug_style = new OutputFormatterStyle('blue');
        $this->getFormatter()->setStyle('debug', $debug_style);
        $warning_style = new OutputFormatterStyle('yellow', null, ['bold']);
        $this->getFormatter()->setStyle('warn', $warning_style);
    }

    public function debug(string $text): void {
        $this->write('['.date('c').'] ', false, OutputInterface::VERBOSITY_DEBUG);
        $this->writeln(sprintf('<debug>DEBUG:%s</debug>', $text), OutputInterface::VERBOSITY_DEBUG);
    }

    public function info(string $text): void {
        $this->write('['.date('c').'] ', false, OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->writeln(sprintf('<info>INFO:%s</info>', $text), OutputInterface::VERBOSITY_VERBOSE);
    }

    public function warning(string $text): void {
        $this->write('['.date('c').'] ', false, OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->writeln(sprintf('<warn>WARN:%s</warn>', $text), OutputInterface::OUTPUT_NORMAL);
    }

    public function error(string $text): void {
        $this->write('['.date('c').'] ', false, OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->writeln(sprintf('<error>ERROR:%s</error>', $text), OutputInterface::OUTPUT_NORMAL);
    }

}
