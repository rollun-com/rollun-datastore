<?php

namespace rollun\test\datastore\Csv;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;
use rollun\test\datastore\Csv\CsvFileObjectAbstractTest;

class CsvModeOnTest extends CsvFileObjectAbstractTest
{

    protected $csvMode = true;

    public function testGetColumns()
    {
        $rows = array(
            ['id', 'val', 'a("@#$%^&*'],
            [0, 'nul', 10],
            [1, 'one', 20]
        );
        $csvFileObject = $this->getCsvFileObject($rows);

        $columns = $csvFileObject->getColumns();
        $this->assertEquals(['id', 'val', 'a("@#$%^&*'], $columns);
    }

    public function testCurrent()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->lock(LOCK_SH);
        $row = $csvFileObject->current();
        $csvFileObject->unlock();
        $this->assertEquals([1, 'one'], $row);
    }

    public function testNext()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->rewind();
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->next();
        $row = $csvFileObject->current();
        $this->assertEquals([2, 'two'], $row);
        $csvFileObject->next();
        $csvFileObject->next();
        $row = $csvFileObject->current();
        $this->assertEquals(4, $row[0]);
        $csvFileObject->next();


        $csvFileObject->unlock();
    }

    public function testRewind()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->next();
        $csvFileObject->rewind();
        $row = $csvFileObject->current();
        $csvFileObject->unlock();
        $this->assertEquals([1, 'one'], $row);
    }

    public function testValid()
    {
        $rows = array(
            ['id', 'val'],
            [1, 'one'],
            [2, 'two']
        );
        $csvFileObject = new CsvFileObject($this->writeDataToCsv($rows), 'r');
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->rewind();
        $csvFileObject->current();
        $csvFileObject->current();
        $csvFileObject->current();
        $this->assertTrue($csvFileObject->valid());
        $csvFileObject->next();
        $this->assertTrue($csvFileObject->valid());
        $csvFileObject->next();
        $this->assertFalse($csvFileObject->valid());
        $csvFileObject->unlock();
    }

    public function testKey()
    {

        $csvFileObject = $this->getCsvFileObject();

        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->rewind();
        $this->assertEquals(1, $csvFileObject->key());
        $csvFileObject->next();
        $this->assertEquals(2, $csvFileObject->key());
        $csvFileObject->next();
        $this->assertEquals(3, $csvFileObject->key());
        $csvFileObject->unlock();
    }

    public function foreachProvider()
    {
        //$count
        return array(
//            [0],
//            [1],
            [500000],
        );
    }

    /**
     * @dataProvider foreachProvider
     */
    public function testForeach($count)
    {

        $rows = array(['id', 'val', 'str']);
        for ($index = 1; $index <= $count; $index++) {
            $val = $index * 10;
            $rows[] = [$index, $val, str_repeat($index, rand(1, 10))]; // rand(1, 100)//1 + $count - $index
        }

        $csvFileObject = $this->getCsvFileObject($rows);

        $csvFileObject->lock(LOCK_SH);
        $savedRows = [];
        $time = time();
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row;
        }
        var_dump('CSV mode ON testForeach ');
        var_dump(time() - $time);
        var_dump(PHP_EOL);
        $expectedRows = $rows;
        unset($expectedRows[0]); //'id', 'val', 'str'
        $csvFileObject->unlock();
        $this->assertEquals($expectedRows, $savedRows);
    }

}
