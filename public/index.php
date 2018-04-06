<?php

// Delegate static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
) {
    return false;
}

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
require_once 'config/env_configurator.php';

/** @var \Interop\Container\ContainerInterface $container */
$container = require 'config/container.php';
\rollun\dic\InsideConstruct::setContainer($container);

const EXAMPLES_DIR = '/src/DataStore/src/Examples';

$uri = $_SERVER['REQUEST_URI'];
$script = str_replace('/', DIRECTORY_SEPARATOR, trim(EXAMPLES_DIR . $uri, '/')) . '.php';
//http://rollun-datastore.loc/Csv/CsvFileObject/Step1 -->> src\DataStore\src\Examples\Csv\CsvFileObject\Step1.php
require $script;
