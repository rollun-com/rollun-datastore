<?php

namespace rollun\test\datastore\Csv\CsvFileObject;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;

abstract class CsvFileObjectAbstractTest extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';
    const CSV_TESTS_FILENAME = 'csvTest.csv';

    protected $fullFilename;
    protected $defaultArray;
    protected $defaultStrings;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->fullFilename = $this->getFullFilename(static::CSV_TESTS_FILENAME);
    }

    public function setUp()
    {

    }

    public function tearDown()
    {

    }

    protected function getFullFilename($filename)
    {
        $dataDir = rtrim(Command::getDataDir(), DIRECTORY_SEPARATOR);
        $CsvTestsDir = $dataDir . DIRECTORY_SEPARATOR . static::CSV_TESTS_DIR;
        if (!is_dir($CsvTestsDir)) {
            mkdir($CsvTestsDir, 0777, true);
        }
        $fullFilename = $CsvTestsDir . DIRECTORY_SEPARATOR . rtrim($filename, '.csv') . '.csv';

        return $fullFilename;
    }

    /**
     *
     * @param array $stringsArray array of strings
     * @return string
     */
    protected function writeToCsvStringByString($stringsArray = null)
    {
        $stream = fopen($this->fullFilename, 'w+');
        flock($stream, LOCK_EX);
        foreach ($stringsArray as $string) {
            fwrite($stream, $string . PHP_EOL);
        }
        fflush($stream);
        flock($stream, LOCK_UN);
        fclose($stream);
        return $this->fullFilename;
    }

    /**
     *
     * @param array $stringsArray array of strings
     * @return string
     */
    protected function readFromCsvStringByString()
    {
        $stream = fopen($this->fullFilename, 'r');
        flock($stream, LOCK_EX);
        $stringsArray = [];
        while (!feof($stream)) {
            $stringsArray[] = fread($stream, 8192);
        }
        flock($stream, LOCK_UN);
        fclose($stream);
        return $stringsArray;
    }

    public function testCsvFileObjectFgetcsvRfc()
    {
        $csvFileObject = new CsvFileObject($this->fullFilename);
        $this->writeToCsvStringByString(
                array(
                    "'id', 'val'",
                    [1, '"Hello\" World!']
                )
        );
        $csvFileObject->lock(LOCK_SH);
        $row0 = $csvFileObject->fgetcsv();
        $row1 = $csvFileObject->fgetcsv();
        $this->assertEquals([1, '"Hello\" World!'], $row1);
        $csvFileObject->unlock();
    }

//
    public function deleteRowProvider()
    {
        //$count, $indexForDelete
        return array(
            [10, 1],
            [10, 2],
            [10, 9],
            [10, 10],
                //[100000, 5000],
        );
    }

//
//    public function deleteRowProvider()
//    {
//        //$count, $indexForDelete
//        return array(
//            [10, 1],
//            [10, 2],
//            [10, 9],
//            [10, 10],
//                //[100000, 5000],
//        );
//    }
//
//    /**
//     *
//     * @param int $count
//     * @param int $indexForDelete
//     * @dataProvider deleteRowProvider
//     */
//    public function testDeleteRow($count, $indexForDelete)
//    {
//        $rows = array(['id', 'val', 'str']);
//        $expectedRows[] = 'shift';
//        for ($index = 1; $index <= $count; $index++) {
//            $val = $index * 10;
//            $rows[] = [$index, $val, str_repeat($index, rand(1, 1000))]; // rand(1, 100)//1 + $count - $index
//            if ($index != $indexForDelete) {
//                $expectedRows[] = $val;
//            }
//        }
//        unset($expectedRows[0]); //'shift'
//        $csvFileObject = $this->getCsvFileObject($rows);
//
//        $savedRows = [];
//        foreach ($csvFileObject as $key => $row) {
//            $savedRows[$key] = $row[1]; //[1];
//        }
//
//        $csvFileObject->deleteRow($indexForDelete);
//
//        $savedRows = [];
//        $csvFileObject->csvModeOn();
//        foreach ($csvFileObject as $key => $row) {
//            $savedRows[$key] = $row[1]; //[1];
//        }
//
//        $this->assertEquals($count - 1, count($savedRows));
//        $this->assertEquals($expectedRows[1], $savedRows[1]);
//        if ($indexForDelete - 1 > 0) {
//            $this->assertEquals($expectedRows[$indexForDelete - 1], $savedRows[$indexForDelete - 1]);
//        }
//        if ($indexForDelete <> $count) {
//            $this->assertEquals($expectedRows[$indexForDelete], $savedRows[$indexForDelete]);
//        }
//        if ($indexForDelete + 2 < $count) {
//            $this->assertEquals($expectedRows[$indexForDelete + 2], $savedRows[$indexForDelete + 2]);
//        }
//
//        $this->assertEquals($expectedRows[$count - 1], $savedRows[$count - 1]);
//        $this->assertEquals(['id', 'val', 'str'], $csvFileObject->getColumns());
//    }
}
