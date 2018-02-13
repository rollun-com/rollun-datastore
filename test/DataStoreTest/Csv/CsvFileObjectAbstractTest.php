<?php

namespace rollun\test\datastore\Csv;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;

abstract class CsvFileObjectAbstractTest extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';
    const CSV_TESTS_FILENAME = 'csvTest.csv';

    protected $fullFilename;
    protected $defaultArray;
    protected $defaultStrings;

    /**
     * 'true' : csvMode - ON
     * 'false' : csvMode - OFF
     *
     * @var bool
     */
    protected $csvMode;

    public function setUp()
    {
        $this->fullFilename = $this->getFullFilename(static::CSV_TESTS_FILENAME);
        $this->defaultArray = array(
            ['id', 'val'],
            [1, 'one'],
            [2, 'two'],
            [3, 'three'],
            [4, 'four']
        );

        $this->defaultStrings = array(
            "id,val",
            "1,one",
            "2,two",
            "3,three",
            "4,four"
        );
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
     * @param array $rows array of strings or arrays
     * @return string
     */
    protected function writeDataToCsv($rows = null)
    {
        $stream = fopen($this->fullFilename, 'w+');
        flock($stream, LOCK_EX);
        foreach ($rows as $fields) {
            if (is_array($fields)) {
                fputcsv($stream, $fields);
            } else {
                fwrite($handle, $fields);
            }
        }
        fflush($stream);
        flock($stream, LOCK_UN);
        fclose($stream);

        return $this->fullFilename;
    }

    protected function getCsvFileObject($rows = null)
    {
        $rows = $rows ?? $this->defaultArray;
        $csvFileObject = new CsvFileObject($this->writeDataToCsv($rows));
        if ($this->csvMode) {
            $csvFileObject->csvModeOn();
        } else {
            $csvFileObject->csvModeOff();
        }
        return $csvFileObject;
    }

    public function testClassCsvFileObject()
    {
        $csvFileObject = $this->getCsvFileObject();

        $this->assertEquals(CsvFileObject::class, get_class($csvFileObject));
        $csvFileObject->lock(LOCK_SH);
        $row = $csvFileObject->fgetcsv();
        $csvFileObject->unlock();
        $this->assertEquals(['id', 'val'], $row);
    }

    public function testCsvMode()
    {
        $csvFileObject = new CsvFileObject($this->writeDataToCsv([]));
        $this->assertTrue($csvFileObject->isCsvMode());

        $csvFileObject->csvModeOff();
        $this->assertFalse($csvFileObject->isCsvMode());
        $csvFileObject->csvModeOn();
        $this->assertTrue($csvFileObject->isCsvMode());

        $csvFileObject->restorePrevCsvMode();
        $this->assertFalse($csvFileObject->isCsvMode());
        $this->expectException(\RuntimeException::class);
        $csvFileObject->restorePrevCsvMode();

        $csvFileObject->csvModeOff();
        $this->assertFalse($csvFileObject->isCsvMode());
    }

    public function testCsvFileObjectFgetcsv()
    {
        $csvFileObject = $this->getCsvFileObject();

        $csvFileObject->lock(LOCK_SH);
        $row = $csvFileObject->fgetcsv();
        $csvFileObject->unlock();
        $this->assertEquals(['id', 'val'], $row);
    }

    public function testDeleteRow()
    {
        $count = 100;

        $indexForDelete = 2;
        $rows = array(['id', 'val']);
        $expectedRows[] = 'shift';

        for ($index = 1; $index <= $count; $index++) {
            $val = $index * 10;
            $rows[] = [$index, $val, str_repeat($index, (rand(1, 100)))];
            if ($index != $indexForDelete) {
                $expectedRows[] = $val;
            }
        }
        unset($expectedRows[0]);
        $csvFileObject = new CsvFileObject($this->writeDataToCsv($rows), 'r');

        $savedRows = [];
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row[1]; //[1];
        }
        $time = time();
        $csvFileObject->deleteRow($indexForDelete);
        var_dump(time() - $time);

        $savedRows = [];
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row[1]; //[1];
        }

        $this->assertEquals($count - 1, count($savedRows));
        $this->assertEquals($expectedRows[1], $savedRows[1]);
        $this->assertEquals($expectedRows[$indexForDelete - 1], $savedRows[$indexForDelete - 1]);
        $this->assertEquals($expectedRows[$indexForDelete], $savedRows[$indexForDelete]);
        $this->assertEquals($expectedRows[$indexForDelete + 2], $savedRows[$indexForDelete + 2]);
        $this->assertEquals($expectedRows[$count - 1], $savedRows[$count - 1]);
    }

}
