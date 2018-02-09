<?php

namespace rollun\test\datastore\Csv;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;

class CsvFileObjectTest extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';
    const CSV_TESTS_FILENAME = 'csvTest.csv';

    protected $fullFilename;

    public function setUp()
    {
        $this->fullFilename = $this->getFullFilename(static::CSV_TESTS_FILENAME);
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

    protected function writeArrayToCsv($rows)
    {
        $stream = fopen($this->fullFilename, 'w+');
        flock($stream, LOCK_EX);
        foreach ($rows as $fields) {
            fputcsv($stream, $fields);
        }
        fflush($stream);
        flock($stream, LOCK_UN);
        fclose($stream);

        return $this->fullFilename;
    }

//
//    public function testClassCsvFileObject()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [0, 'nul'],
//            [1, 'one']
//        );
//        $this->writeArrayToCsv($rows);
//        $csvFileObject = new CsvFileObject($this->fullFilename, 'r');
//        $this->assertEquals(CsvFileObject::class, get_class($csvFileObject));
//        $csvFileObject->lock(LOCK_SH);
//        $row = $csvFileObject->fgetcsv();
//        $csvFileObject->unlock();
//        $this->assertEquals(['id', 'val'], $row);
//    }
//
//    public function testGetColumns()
//    {
//        $rows = array(
//            ['id', 'val', 'a("@#$%^&*'],
//            [0, 'nul', 10],
//            [1, 'one', 20]
//        );
//        $this->writeArrayToCsv($rows);
//        $csvFileObject = new CsvFileObject($this->fullFilename, 'r');
//        $columns = $csvFileObject->getColumns();
//        $this->assertEquals(['id', 'val', 'a("@#$%^&*'], $columns);
//    }
//
//    public function testCurrent()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $row = $csvFileObject->current();
//        $csvFileObject->unlock();
//        $this->assertEquals([1, 'one'], $row);
//    }
//
//    public function testNext()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $csvFileObject->next();
//        $row = $csvFileObject->current();
//        $csvFileObject->unlock();
//        $this->assertEquals([2, 'two'], $row);
//    }
//
//    public function testRewind()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $csvFileObject->next();
//        $csvFileObject->rewind();
//        $row = $csvFileObject->current();
//        $csvFileObject->unlock();
//        $this->assertEquals([1, 'one'], $row);
//    }
//
//    public function testValid()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $csvFileObject->rewind();
//        $csvFileObject->current();
//        $csvFileObject->current();
//        $csvFileObject->current();
//        $this->assertTrue($csvFileObject->valid());
//        $csvFileObject->next();
//        $this->assertTrue($csvFileObject->valid());
//        $csvFileObject->next();
//        $this->assertFalse($csvFileObject->valid());
//        $csvFileObject->unlock();
//    }
//
//    public function testKey()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two'],
//            [3, 'three'],
//            [4, 'four']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $csvFileObject->rewind();
//        $this->assertEquals(1, $csvFileObject->key());
//        $csvFileObject->next();
//        $this->assertEquals(2, $csvFileObject->key());
//        $csvFileObject->next();
//        $this->assertEquals(3, $csvFileObject->key());
//        $csvFileObject->unlock();
//    }
//
//    public function testForeach()
//    {
//        $rows = array(
//            ['id', 'val'],
//            [1, 'one'],
//            [2, 'two']
//        );
//        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');
//        $csvFileObject->lock(LOCK_SH);
//        $savedRows = [];
//        foreach ($csvFileObject as $key => $row) {
//            $savedRows[$key] = $row;
//        }
//        unset($rows[0]); //delete columns names
//        $expectedRows = $rows;
//        $csvFileObject->unlock();
//        $this->assertEquals($expectedRows, $savedRows);
//    }

    public function testDeleteRow()
    {
        $indexForDelete = 101;

        $rows = array(['id', 'val']);
        $expectedRows[] = 'shift';
        $count = 1000;
        for ($index = 1; $index <= $count; $index++) {
            $val = $index * 10;
            $rows[] = [$index, $val, str_repeat($index, (rand(1, 100)))]; //rand(1, 100)
            if ($index != $indexForDelete) {
                $expectedRows[] = $val;
            }
        }
//        $rows = [
//            ['000000'],
//            ['1000'],
//            ['200'],
//            ['30'],
//            ['4'],
//            ['5'],
//        ];
        unset($expectedRows[0]);





        $csvFileObject = new CsvFileObject($this->writeArrayToCsv($rows), 'r');







        $savedRows = [];
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row[1];
        }
        $time = time();
        $csvFileObject->deleteRow($indexForDelete);
        var_dump(time() - $time);

        $savedRows = [];
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row[1];
        }

        $this->assertEquals($count - 1, count($savedRows));

        $this->assertEquals($expectedRows[1], $savedRows[1]);
        $this->assertEquals($expectedRows[$indexForDelete - 1], $savedRows[$indexForDelete - 1]);
        $this->assertEquals($expectedRows[$indexForDelete], $savedRows[$indexForDelete]);
        $this->assertEquals($expectedRows[$indexForDelete + 2], $savedRows[$indexForDelete + 2]);
        $this->assertEquals($expectedRows[$count - 1], $savedRows[$count - 1]);
    }

}
