<?php

namespace rollun\test\datastore\Csv;

use rollun\datastore\Csv\CsvFileManager;
use rollun\installer\Command;

class CsvFileManagerTest extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';

    public function csvFileObjectFgetcsvRfcProvider()
    {
        return array(
            ['data', 'file.csv', 'data' . DIRECTORY_SEPARATOR . 'file.csv'],
            [DIRECTORY_SEPARATOR . 'data', 'file.csv', DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'file.csv'],
            ['data' . DIRECTORY_SEPARATOR, 'file.csv', 'data' . DIRECTORY_SEPARATOR . 'file.csv'],
            ['', 'file.csv', '..\file.csv'],
            [null, 'file.csv', '..\file.csv'],
        );
    }

    /**
     * @dataProvider csvFileObjectFgetcsvRfcProvider
     */
    public function testGetFullFilename($dirname, $filename, $expected)
    {
        $csvFileManager = new CsvFileManager();
        $result = $csvFileManager->getFullFilename($filename, $dirname);
        $this->assertEquals($expected, $result);
    }

    public function testGetFullFilenameException1()
    {
        $csvFileManager = new CsvFileManager();
        $this->expectException(\Exception::class);
        $result = $csvFileManager->getFullFilename('\\' . 'filename.csv');
    }

    public function testGetFullFilenameException2()
    {
        $csvFileManager = new CsvFileManager();
        $this->expectException(\Exception::class);
        $result = $csvFileManager->getFullFilename('/' . 'filename.csv');
    }

    public function createDirDataProvider()
    {
        return array(
            ['data/unittests/test1', 'data/unittests/test1'],
            ['data/unittests/test1/test2/test3', 'data/unittests/test1'],
        );
    }

    /**
     * @dataProvider createDirDataProvider
     */
    public function testCreateDir($dirnameForCreate, $dirnameForDelete)
    {
        $this->assertFalse(file_exists($dirnameForCreate), 'Dir exists: ' . $dirnameForCreate);
        $csvFileManager = new CsvFileManager();
        $csvFileManager->createDir($dirnameForCreate);
        $this->assertTrue(file_exists($dirnameForCreate));
        $csvFileManager->deleteRecursively($dirnameForDelete);
        $this->assertFalse(file_exists($dirnameForCreate));
    }

    public function testAll()
    {
        $csvFileManager = new CsvFileManager();
        if (file_exists('data/unittests/CsvFileManagerTest')) {
            $csvFileManager->deleteRecursively('data/unittests/CsvFileManagerTest');
        }
        $dirnameForCreate = 'data/unittests/CsvFileManagerTest/testAll';
        $this->assertFalse(file_exists($dirnameForCreate), 'Dir exists: ' . $dirnameForCreate);
        $csvFileManager->createDir($dirnameForCreate);
        $this->assertTrue(file_exists($dirnameForCreate));
        $fullFilename = $csvFileManager->getFullFilename('test.txt', $dirnameForCreate);
        $stream = $csvFileManager->createAndOpenFile($fullFilename);
        $this->assertTrue(file_exists($fullFilename));
        $csvFileManager->closeStream($stream, $fullFilename);
        $csvFileManager->deleteRecursively('data/unittests/CsvFileManagerTest');
        $this->assertFalse(file_exists($dirnameForCreate));
    }

}
