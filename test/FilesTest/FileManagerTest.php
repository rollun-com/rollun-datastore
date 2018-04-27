<?php

namespace rollun\test\files;

use rollun\files\FileManager;

class FileManagerTest extends \PHPUnit_Framework_TestCase
{

    const CSV_TESTS_DIR = 'CsvTests';

    public function csvFileObjectFgetcsvRfcProvider()
    {
        return array(
            ['file.csv',
                ['file.csv']
            ],
            ['/file.csv', ['/file.csv']],
            ['/file.csv', ['\file.csv']],
            ['C:/file.csv', [' C:/', ' \file.csv']],
            ['C:/dir/file.csv', [' C:/', 'dir', ' \file.csv']],
            ['C:/dir/file.csv', [' C:/', '\dir/', ' \file.csv']],
            ['ftp://dir/file.csv', ['ftp://', '\dir/', ' \file.csv']],
            ['http://file.csv', ['http://', ' \file.csv']],
            ['php://input', ['php://input']],
        );
    }

    /**
     * @dataProvider csvFileObjectFgetcsvRfcProvider
     */
    public function testJoinPath($expected, $arguments)
    {
        $csvFileManager = new FileManager();
        $result = call_user_func_array([$csvFileManager, 'joinPath'], $arguments);
        $this->assertEquals($expected, $result);
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
        $csvFileManager = new FileManager();
        if (file_exists($dirnameForCreate)) {
            $csvFileManager->deleteDirRecursively($dirnameForCreate);
        }
        $csvFileManager->createDir($dirnameForCreate);
        $this->assertTrue(file_exists($dirnameForCreate));
        $csvFileManager->deleteDirRecursively($dirnameForDelete);
        $this->assertFalse(file_exists($dirnameForCreate));
    }

    public function testAll()
    {
        $csvFileManager = new FileManager();
        if (file_exists('data/unittests/FileManagerTest')) {
            $csvFileManager->deleteDirRecursively('data/unittests/FileManagerTest');
        }
        $dirnameForCreate = 'data/unittests/FileManagerTest/testAll';
        $this->assertFalse(file_exists($dirnameForCreate), 'Dir exists: ' . $dirnameForCreate);
        $csvFileManager->createDir($dirnameForCreate);
        $this->assertTrue(file_exists($dirnameForCreate));
        $fullFilename = $csvFileManager->joinPath($dirnameForCreate, 'test.txt');
        $stream = $csvFileManager->createAndOpenFile($fullFilename);
        $this->assertTrue(file_exists($fullFilename));
        $csvFileManager->closeStream($stream, $fullFilename);
        $csvFileManager->deleteDirRecursively('data/unittests/FileManagerTest');
        $this->assertFalse(file_exists($dirnameForCreate));
    }

}
