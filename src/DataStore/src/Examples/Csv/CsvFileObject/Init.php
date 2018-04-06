<?php

namespace rollun\datastore\Examples\Csv\CsvFileObject;

use rollun\installer\Command;

const CSV_EXAMPLES_DIR = 'CsvExamples';
const CSV_EXAMPLES_FILENAME = 'CsvFileObject.csv';

/**
 *
 * @param array $rows array of strings or arrays
 * @return string
 */
function writeDataToCsv($rows = null)
{
    $csvDataDir = rtrim(Command::getDataDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . CSV_EXAMPLES_DIR;
    if (!is_dir($csvDataDir)) {
        mkdir($csvDataDir, 0777, true);
    }
    $fullFilename = $csvDataDir . DIRECTORY_SEPARATOR . CSV_EXAMPLES_FILENAME;

    $stream = fopen($fullFilename, 'w+');
    foreach ($rows as $fields) {
        if (is_array($fields)) {
            fputcsv($stream, $fields);
        } else {
            fwrite($stream, $fields);
        }
    }
    fflush($stream);
    fclose($stream);
    return $fullFilename;
}

function getArray($count, $length)
{
    $rows = array(['id', 'str']);
    for ($index = 1; $index <= $count; $index++) {
        $rows[] = [$index, str_repeat('.', rand(1, 2 * $length))]; // rand(1, 100)//1 + $count - $index
    }
    return $rows;
}

echo "----------------------------------------------------------------------------------------------------------------<br>" . PHP_EOL;
echo "<h4>" . __FILE__ . "</h4>" . PHP_EOL;
echo "==================================================================<br><br>" . PHP_EOL;
