<?php

require_once __DIR__.'/vendor/autoload.php';
use Phine\Phar;
use Symfony\Component\Finder\Finder;

$phar_filename = 'doxie-consumer.phar';

if(file_exists(__DIR__.DIRECTORY_SEPARATOR.$phar_filename)){
    // if we already have a phar file, lets delete_scan it
    unlink(__DIR__.DIRECTORY_SEPARATOR.$phar_filename);
}

// create a new Phar instance
$builder = Phar\Builder::create($phar_filename);

// add files from src/ to phar
$src_iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__.DIRECTORY_SEPARATOR.'src');
foreach($src_iterator as $src_file){
    $builder->addFile($src_file, str_replace(__DIR__, '', $src_file));
}

// add files from vendor/ to phar
$vendor_iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->name('cacert.pem')    // needed for guzzle to work
    ->exclude(array('tests', 'Tests', 'docs'))
    ->in(__DIR__.DIRECTORY_SEPARATOR.'vendor');

foreach($vendor_iterator as $vendor_file){
    $builder->addFile($vendor_file, str_replace(__DIR__, '', $vendor_file));
}

$builder->addFile(__DIR__.'/.env', '/.env');

$builder->setStub(
    Phar\Stub::create()
        ->mapPhar($phar_filename)
        ->addRequire('src/consumer.php')
        ->selfExtracting()
        ->getStub()
);