<?php

namespace rollun\test\files\FileObject\StringOperations;

use rollun\test\files\FileObject\StringOperations\StringOperationsAbstractTest;

class InsertStringTest extends StringOperationsAbstractTest
{

    public function stringsCountProvider()
    {
        //$string, $expectedCount
        return array(
            ['', 0],
            ["\n", 1], ['0', 1], ["0\n", 1],
            ["\n\n", 2], ["0\n1", 2], ["0\n1\n", 2],
            ["\n\n\n", 3], ["0\n\n\n", 3], ["\n\n2", 3], ["0\n1\n2", 3], ["0\n1\n2\n", 3],
        );
    }

    /**
     *
     * @dataProvider stringsCountProvider
     */
    public function testStringsCount($string, $expectedCount)
    {
        $fileObject = $this->getFileObject();
        $fileObject->ftruncate(0);
        $fileObject->fwriteWithCheck($string);
        $actualCount = $fileObject->getStringsCount();
        $this->assertEquals($expectedCount, $actualCount);
    }

    public function eolProvider()
    {
        //$indexForInsert, $stringForInsert,$stringInFile, $expectedString
        return array(
            [0, 'i', "", "i\n"],
            [null, 'i', "", "i\n"],
            [0, "i", "\n", "i\n\n"],
            [null, "i", "\n", "\ni\n"],
            [null, "i", "0123", "0123\ni\n"],
        );
    }

    /**
     *
     * @dataProvider eolProvider
     */
    public function testEol($indexForInsert, $stringForInsert, $stringInFile, $expectedString)
    {
        $fileObject = $this->getFileObject();

        $fileObject->fwriteWithCheck($stringInFile);
        $fileObject->insertString($stringForInsert, $indexForInsert);
        $fileObject->fseekWithCheck(0);
        $actualString = $fileObject->fread(10);
        $this->assertEquals($expectedString, $actualString);
    }

    public function getFileSizeProvider()
    {
        //$stringInFile, $expectedFileSize
        return array(
            ["", 0],
            ["\n", 1],
            ["0", 1],
            ["0\n", 2],
            ["\n\n", 2],
            ["\n1\n", 3],
            ["1234567890", 10],
        );
    }

    /**
     *
     * @dataProvider getFileSizeProvider
     */
    public function testGetFileSize($stringInFile, $expectedFileSize)
    {
        $fileObject = $this->getFileObject();
        $fileObject->fwriteWithCheck($stringInFile);
        $actualFileSize = $fileObject->getFileSize();
        $this->assertEquals($actualFileSize, $expectedFileSize);
    }

}
