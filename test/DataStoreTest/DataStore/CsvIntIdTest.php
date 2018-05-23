<?php

namespace rollun\test\datastore\DataStore;

use rollun\test\datastore\DataStore\CsvBaseTest;

class CsvIntIdTest extends CsvBaseTest
{
    protected $entity = 'testCsvIntId';

    public function testCheckItegrity()
    {
        $this->_initObject();
        $this->assertTrue(
            $this->object->checkIntegrityData()
        );
    }

    public function testInsertLastRowAndCheckIntegrity()
    {
        $this->_initObject();
        $itemData = array(
            'id' => 1000,
            'anotherId' => null,
            'fFloat' => 1000.01,
            'fString' => ''
        );
        $this->object->create($itemData);
        $this->assertTrue(
            $this->object->checkIntegrityData()
        );
    }

    public function testInsertRowIntoMiddleListAndCheckIntegrity()
    {
        $this->_initObject();
        $itemData = array(
            'id' => 1000,
            'anotherId' => null,
            'fFloat' => 1000.01,
            'fString' => ''
        );
        $this->object->create($itemData);
        $itemData = array(
            'id' => 100,
            'anotherId' => null,
            'fFloat' => 100.01,
            'fString' => ''
        );
        $this->object->create($itemData);
        $this->assertTrue(
            $this->object->checkIntegrityData()
        );
    }

    public function testIntegrityNotSortedData()
    {
        $itemData = $this->_itemsArrayDelault;
        $itemData[] = array(
            'id' => 1000,
            'anotherId' => null,
            'fFloat' => 1000.01,
            'fString' => ''
        );
        $itemData[] = array(
            'id' => 100,
            'anotherId' => null,
            'fFloat' => 100.01,
            'fString' => ''
        );
        $this->_initObject($itemData);
        $this->setExpectedException('\rollun\datastore\DataStore\DataStoreException');
        $this->object->checkIntegrityData();
    }

    public function testIntegrity_TryingToWriteNotIntegerPrimaryKey()
    {
        $this->_initObject();
        $itemData = array(
            'id' => uniqid(),
            'anotherId' => null,
            'fFloat' => 1000.01,
            'fString' => ''
        );
        $this->setExpectedException('\rollun\datastore\DataStore\DataStoreException');
        $this->object->create($itemData);
    }
}