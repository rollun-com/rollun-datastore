<?php

namespace rollun\test\files\FileObject;

class ChangeableTest extends AbstractTest
{

    public function moveSubStrProvider()
    {
        //$charPosFrom, $newCharPos, $stringInFile, $expected
        return array(
            [3, 1, '012345', '0345'],
            [5, 0, '012345', '5'],
            [1, 3, '012345', '01212345'],
            [1, 5, '012345', '0123412345'],
            [1, 6, '012345', '01234512345'],
            [0, 6, '012345', '012345012345'],
            [0, 1, '012345', '0012345'],
            [0, 10, '012345', "012345    012345"],
            [0, 3, "012345678", '012012345678'],
            [0, 7, '012345', "012345 012345"],
            [0, 4, "0123456789ABCD", "01230123456789ABCD"],
            [0, 4, "0123456789ABCD", "01230123456789ABCD"],
        );
    }

    /**
     * @dataProvider moveSubStrProvider
     */
    public function testMoveSubStr($charPosFrom, $newCharPos, $stringInFile, $expected)
    {
        $fileObject = $this->getFileObject();
        $fileObject->fwriteWithCheck($stringInFile);
        $fileObject->moveSubStr($charPosFrom, $newCharPos);
        $fileObject->fseek(0);
        $actual = $fileObject->fread(100);
        $this->assertEquals($expected, $actual);
    }

    public function truncateWithCheckProvider()
    {
        //$stringInFile, $newSize, $expectedString
        return array(
            ["123", 0, ""], ["123", 1, "1"], ["123", 2, "12"], ["123", 3, "123"],
            ["\n", 0, ""], ["\n", 1, "\n"],
        );
    }

    /**
     * @dataProvider truncateWithCheckProvider
     */
    public function testTruncateWithCheck($stringInFile, $newSize, $expectedString)
    {
        $fileObject = $this->getFileObject();

        $fileObject->fwriteWithCheck($stringInFile);
        $fileObject->truncateWithCheck($newSize);
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
