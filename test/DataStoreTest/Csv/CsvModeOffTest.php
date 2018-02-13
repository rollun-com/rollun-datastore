<?php

namespace rollun\test\datastore\Csv;

use rollun\datastore\Csv\CsvFileObject;
use rollun\installer\Command;
use rollun\test\datastore\Csv\CsvFileObjectAbstractTest;

class CsvModeOffTest extends CsvFileObjectAbstractTest
{

    protected $csvMode = false;

    public function testGetColumns()
    {
        $rows = array(
            ['id', 'val', 'a("@#$%^&*'],
            [0, 'nul', 10],
            [1, 'one', 20]
        );
        $csvFileObject = $this->getCsvFileObject($rows);

        $columns = $csvFileObject->getColumns();
        $this->assertEquals('id,val,"a(""@#$%^&*"', $columns);
    }

    public function testCurrent()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->rewind();
        $csvFileObject->next();
        $row = $csvFileObject->current();
        $csvFileObject->unlock();
        $this->assertEquals('1,one', $row);
    }

    public function testNext()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->rewind();
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->next();
        $row = $csvFileObject->current();
        $csvFileObject->unlock();

        $this->assertEquals('1,one', $row);
    }

    public function testRewind()
    {
        $csvFileObject = $this->getCsvFileObject();
        $csvFileObject->lock(LOCK_SH);
        $csvFileObject->next();
        $csvFileObject->rewind();
        $row = $csvFileObject->current();
        $csvFileObject->unlock();
        $str = 'id,val';

        $this->assertEquals('id,val', $row);
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
        $this->assertEquals(0, $csvFileObject->key());
        $csvFileObject->next();
        $this->assertEquals(1, $csvFileObject->key());
        $csvFileObject->next();
        $this->assertEquals(2, $csvFileObject->key());
        $csvFileObject->next();
        $this->assertEquals(3, $csvFileObject->key());
        $csvFileObject->unlock();
    }

    public function testForeach()
    {

        $csvFileObject = $this->getCsvFileObject();

        $csvFileObject->lock(LOCK_SH);
        $savedRows = [];
        foreach ($csvFileObject as $key => $row) {
            $savedRows[$key] = $row;
        }

        $expectedRows = $this->defaultStrings;
        $csvFileObject->unlock();
        $this->assertEquals($expectedRows, $savedRows);
    }

}
