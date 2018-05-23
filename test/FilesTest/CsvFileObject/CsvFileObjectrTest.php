<?php

namespace rollun\test\files\CsvFileObject;

use rollun\test\files\CsvFileObject\CsvFileObjectAbstractTest;
use rollun\files\CsvFileObject;

class CsvFileObjectrTest extends CsvFileObjectAbstractTest
{

    public function getColumnsProvider()
    {
        //$columsStrings
        return array(
            ["val\n"],
            ["val"],
            ["id,val\n"],
            ["id,val"],
            ["val\nA\n", ['A']],
            ["val\nA", ['A']],
            ["id,val\n1,A", ['1', 'A']],
            ["id,val\n0123,AB CD", ['0123', 'AB CD']],
        );
    }

    /**
     * @dataProvider getColumnsProvider
     */
    public function testGetColumns($columsStrings)
    {
        $csvFileObject = $this->getCsvFileObject($columsStrings);
        $expected = explode("\n", $columsStrings)[0];
        $actual = implode(',', $csvFileObject->getColumns());
        $this->assertEquals($expected, $actual);
    }

    public function getRowProvider()
    {
        //$columsStrings
        return array(
            ["val\nA\n", ['A']],
            ["val\nA", ['A']],
            ["id,val\n1,A", ['1', 'A']],
            ["id,val\n0123,AB CD", ['0123', 'AB CD']],
        );
    }

    /**
     * @dataProvider getRowProvider
     */
    public function testGetRow($stringInFile, $arrayExpected)
    {
        $csvFileObject = $this->getCsvFileObject($stringInFile);
        $arrayActual = $csvFileObject->getRow(1);
        $this->assertEquals($arrayExpected, $arrayActual);
    }

    public function createNewCsvFileProvider()
    {
        //$columsArray
        return array(
            [["val"]],
            [["id", "val"]],
        );
    }

    /**
     * @dataProvider createNewCsvFileProvider
     */
    public function testCreateNewCsvFile($columsArray)
    {
        $fullFilename = $this->makeFullFileName();
        @unlink($fullFilename);

        CsvFileObject::createNewCsvFile($fullFilename, $columsArray);
        $arrayExpected = $columsArray;
        $csvFileObject = new CsvFileObject($fullFilename);
        $arrayActual = $csvFileObject->getColumns();
        $this->assertEquals($arrayExpected, $arrayActual);
    }

}
