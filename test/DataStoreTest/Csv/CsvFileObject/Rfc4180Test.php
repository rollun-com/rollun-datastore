<?php

namespace rollun\test\datastore\Csv\CsvFileObject;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;

class Rfc4180Test extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';
    const CSV_TESTS_FILENAME = 'csvTest.csv';

    protected $fullFilename;

    public function setUp()
    {
        $this->setFullFilename(static::CSV_TESTS_FILENAME);
        //$this->csvFileObject = new CsvFileObject($this->fullFilename);
    }

    public function tearDown()
    {
        $stream = fopen($this->fullFilename, 'r+');
        flock($stream, LOCK_EX);
        ftruncate($stream, 0);
        flock($stream, LOCK_UN);
        fclose($stream);
    }

    protected function setFullFilename($filename)
    {
        $dataDir = rtrim(Command::getDataDir(), DIRECTORY_SEPARATOR);
        $CsvTestsDir = $dataDir . DIRECTORY_SEPARATOR . static::CSV_TESTS_DIR;
        if (!is_dir($CsvTestsDir)) {
            mkdir($CsvTestsDir, 0777, true);
        }
        $fullFilename = $CsvTestsDir . DIRECTORY_SEPARATOR . rtrim($filename, '.csv') . '.csv';
        if (!is_file($fullFilename)) {
            $stream = fopen($fullFilename, 'w');
            fclose($stream);
        }
        $this->fullFilename = $fullFilename;
    }

    /**
     *
     * @param array $stringsArray array of strings
     * @return string
     */
    protected function writeToCsvStringByString($stringsArray = null)
    {
        $stream = fopen($this->fullFilename, 'c+');
        flock($stream, LOCK_EX);
        ftruncate($stream, 0);
        foreach ($stringsArray as $string) {
            fwrite($stream, $string . "\n");
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
    protected function readFromCsvAsString()
    {
        $stream = fopen($this->fullFilename, 'r');
        flock($stream, LOCK_EX);
        while (!feof($stream)) {
            $csvString = fread($stream, 8192);
        }
        flock($stream, LOCK_UN);
        fclose($stream);
        return $csvString;
    }

    public function csvFileObjectFgetcsvRfcProvider()
    {
        return array(
            array(
                '"Hello,World!",Hello;World!,"Hello\""World\""!","Hello\\\'World\\\'!","Hello' . "\n" . 'World!"',
                ['Hello,World!', 'Hello;World!', 'Hello\"World\"!', "Hello\'World\'!", "Hello\nWorld!"]
            ),
        );
    }

    /**
     * @dataProvider csvFileObjectFgetcsvRfcProvider
     */
    public function testCsvFileObjectFgetcsvRfc($dataIn, $dataOut)
    {
        $columsNames = "val1,val2,val3,val4,val5";
        $dataInArray = [$dataIn];
        array_unshift($dataInArray, $columsNames);
        $this->writeToCsvStringByString(
                $dataInArray
        );
        $csvFileObject = new CsvFileObject($this->fullFilename);
        $csvFileObject->lock(LOCK_EX);
        $row = $csvFileObject->fgetcsv();
        $csvFileObject->unlock();
        $this->assertEquals($dataOut, $row);
    }

    /**
     * @dataProvider csvFileObjectFgetcsvRfcProvider
     */
    public function testCsvFileObjectFputcsvRfc($dataIn, $dataOut)
    {
        $columsNames = "val1,val2,val3,val4,val5";
        $this->writeToCsvStringByString([$columsNames]);
        $csvFileObject = new CsvFileObject($this->fullFilename);
        $csvFileObject->fputcsv($dataOut);
        $csvString = $this->readFromCsvAsString();
        $this->assertEquals($columsNames . "\n" . $dataIn . "\n", $csvString);
        $csvFileObject->unlock();
    }

    public function testCsvIteration()
    {
        $csvFileObject = new CsvFileObject($this->fullFilename);
        $columsNames = "val1,val2,val3,val4,val5";
        $csvFileObject->fwrite($columsNames . "\n" . '"Hello,World!",Hello;World!,"Hello\""World\""!","Hello\\\'World\\\'!","Hello' . "\n" . 'World!"' . "\n");
        $csvFileObject->rewind();
        $expected = array(array('Hello,World!', 'Hello;World!', 'Hello\"World\"!', "Hello\'World\'!", "Hello\nWorld!"));
        $actual = array();
        foreach ($csvFileObject as $row) {
            $actual[] = $row;
        }
        $this->assertEquals($expected, $actual);
    }

    public function foreachProvider()
    {
        //$count
        return array(
//            [0],
//            [1],
            [5000],
        );
    }

//    /**
//     * @dataProvider foreachProvider
//     */
//    public function testForeach($count)
//    {
//
//        $rows = array(['id', 'val', 'str']);
//        for ($index = 1; $index <= $count; $index++) {
//            $val = $index * 10;
//            $rows[] = [$index, $val, str_repeat($index, rand(1, 10))]; // rand(1, 100)//1 + $count - $index
//        }
//
//        $csvFileObject = $this->getCsvFileObject($rows);
//
//        $csvFileObject->lock(LOCK_SH);
//        $savedRows = [];
//        $time = time();
//        foreach ($csvFileObject as $key => $row) {
//            $savedRows[$key] = $row;
//        }
//        var_dump('CSV mode ON testForeach ');
//        var_dump(time() - $time);
//        var_dump(PHP_EOL);
//        $expectedRows = $rows;
//        unset($expectedRows[0]); //'id', 'val', 'str'
//        $csvFileObject->unlock();
//        $this->assertEquals($expectedRows, $savedRows);
//    }
}
