<?php

namespace rollun\test\files;

use rollun\files\FileObject;
use rollun\files\FileManager;

class FileObjectTest extends \PHPUnit_Framework_TestCase
{

    public function getFileObject($stringsArray)
    {
        $fileManager = new FileManager;
        $fileManager->createDir('data/FilesTests/FileObjectTest');
        $fullFilename = $fileManager->joinPath('data/FilesTests/FileObjectTest', 'fileObjectTest.txt');
        $stream = $fileManager->createAndOpenFile($fullFilename, true);
        foreach ($stringsArray as $string) {
            fwrite($stream, $string . "\n");
        }
        $fileManager->closeStream($stream);
        $fileObject = new FileObject($fullFilename);
        $fileObject->setFlags(\SplFileObject::READ_AHEAD);
        return $fileObject;
    }

    public function deleteRowProvider1()
    {
        //$maxIndex, $indexForDelete
        return array(
            [10, 0],
            [10, 1],
            [10, 10],
            // [10000, 500],
            [10, 5],
        );
    }

    /**
     *
     * @param int $maxIndex
     * @param int $indexForDelete
     * @dataProvider deleteRowProvider1
     */
    public function test1DeleteRow($maxIndex, $indexForDelete)
    {

        for ($index = 0; $index <= $maxIndex; $index++) {
            $val = str_repeat($index, rand(1, 1000)); // rand(1, 100)//1 + $maxIndex - $index
            $rows[] = $val;
            if ($index != $indexForDelete) {
                $expectedRows[] = $val . "\n";
            }
        }
        $fileObject = $this->getFileObject($rows);

        $savedRows = [];
        foreach ($fileObject as $key => $row) {
            $savedRows[$key] = $row; //[1];
        }

        $fileObject->deleteRow($indexForDelete);

        $savedRows = [];
        //$fileObject->csvModeOn();
        foreach ($fileObject as $key => $row) {
            if ($row !== false) {
                $savedRows[$key] = $row; //[1];
            }
        }

        $this->assertEquals($maxIndex, count($savedRows) - 1);
        $this->assertEquals($expectedRows[0], $savedRows[0]);
        if ($indexForDelete > 0) {
            $this->assertEquals($expectedRows[$indexForDelete - 1], $savedRows[$indexForDelete - 1]);
        }
        if ($indexForDelete < $maxIndex) {
            $this->assertEquals($expectedRows[$indexForDelete], $savedRows[$indexForDelete]);
        }

        if ($indexForDelete < $maxIndex - 1) {
            $this->assertEquals($expectedRows[$indexForDelete + 1], $savedRows[$indexForDelete + 1]);
        }

        $this->assertEquals($expectedRows[$maxIndex - 1], $savedRows[$maxIndex - 1]);
    }

    public function deleteRowProvider2()
    {
        //$maxIndex, $indexForDelete
        return array(
            [0, ["0", "1"], "1\n"],
            [1, ["0", "1"], "0\n"],
            [0, ["0"], ""],
            [0, [""], ""],
            [0, ["0", ''], "\n"],
            [1, ["0", ''], "0\n"],
            [0, ['', ''], "\n"],
            [1, ['', ''], "\n"],
        );
    }

    /**
     *
     * @param int $maxIndex
     * @param int $indexForDelete
     * @dataProvider deleteRowProvider2
     */
    public function test2DeleteRow($indexForDelete, $strings, $expected)
    {
        $fileObject = $this->getFileObject($strings);
        $fileObject->deleteRow($indexForDelete);
        $fileObject = new \SplFileObject($fileObject->getRealPath(), 'r');
        $string = $fileObject->fread(10);

        $this->assertEquals($expected, $string);
    }

}
