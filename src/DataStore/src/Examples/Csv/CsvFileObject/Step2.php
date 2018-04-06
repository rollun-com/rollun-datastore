<?php

namespace rollun\datastore\Examples\Csv\CsvFileObject;

use rollun\datastore\Csv\CsvFileObject;

require_once 'Init.php';

echo "----------------------------------------------------------------------------------------------------------------<br>" . PHP_EOL;
echo "<h4>" . __FILE__ . "</h4>" . PHP_EOL;


$rows = getArray(10, 100);
$fullFilename = writeDataToCsv($rows);
$csvFileObject = new CsvFileObject($fullFilename);

var_dump($csvFileObject->current());

$count = iterator_count($csvFileObject);
var_dump("Your CsvFileObject has $count lines");


echo "==================================================================<br><br>" . PHP_EOL;



