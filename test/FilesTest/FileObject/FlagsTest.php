<?php

namespace rollun\test\files\FileObject;

class FlagsTest extends AbstractTest
{

    public function stringsRowProvider()
    {

        //$flags, \SplFileObject::DROP_NEW_LINE  \SplFileObject::SKIP_EMPTY \SplFileObject::READ_AHEAD
        //$strings
        return array(
            [0, [""]],
            [\SplFileObject::DROP_NEW_LINE, [""]],
            [0, ["1"]],
            [\SplFileObject::DROP_NEW_LINE, ["1"]],
            [0, ["", '2'], ['2']],
            [\SplFileObject::DROP_NEW_LINE, ["", '2'], ['2']],
            [0, ["\n", '']],
            [\SplFileObject::DROP_NEW_LINE, ["\n"], ['', '']],
            [0, ["1\n"], ["1\n", '']],
            [\SplFileObject::DROP_NEW_LINE, ["1\n"], ["1", '']],
            [0, ["\n", '2']],
            [\SplFileObject::DROP_NEW_LINE, ["\n", '2'], ['', '2']],
            [0, ["1", '2'], ['12']],
            [\SplFileObject::DROP_NEW_LINE, ["1", '2'], ['12']],
            [\SplFileObject::SKIP_EMPTY, [""]],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, [""]],
            [\SplFileObject::SKIP_EMPTY, ["1"]],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["1"]],
            [\SplFileObject::SKIP_EMPTY, ["", '2'], ['2']],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["", '2'], ['2']],
            [\SplFileObject::SKIP_EMPTY, ["\n", '']],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["\n"], [false]],
            [\SplFileObject::SKIP_EMPTY, ["1\n"], ["1\n", '']],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["1\n"], ["1", '']],
            [\SplFileObject::SKIP_EMPTY, ["\n", '2']],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["\n", '2'], ['2']],
            [\SplFileObject::SKIP_EMPTY, ["1", '2'], ['12']],
            [\SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY, ["1", '2'], ['12']],
        );
    }

    /**
     *
     * @dataProvider stringsRowProvider
     */
    public function testFlags($flags, $strings, $expected = null)
    {
        $expected = $expected ?? $strings;
        $fileObject = $this->getFileObject();
        $fileObject->ftruncate(0);
        foreach ($strings as $string) {
            $fileObject->fwrite($string);
        }
        $fileObject->fseek(0);
        $fileObject->setFlags($flags);
        $savedRows = [];

        foreach ($fileObject as $key => $row) {
            $savedRows[$key] = $row; //[1];
        }


        $this->assertEquals($expected, $savedRows);
    }

    public function changeFileSize()
    {
        //$fileSize, $newFileSize
        return array(
            [1, 1],
            [10, 255],
            [10, 11],
            [0, 10]
        );
    }

    /**
     *
     * @dataProvider changeFileSize
     */
    public function testChangeFileSizeReturn($fileSize, $newFileSize)
    {

        $fileObject = $this->getFileObject();
        $fileObject->ftruncate(0);
        $string = str_repeat('A', $fileSize);
        $fileObject->fwrite($string);
        $expected = $newFileSize - $fileSize;
        $actual = $fileObject->makeFileLonger($newFileSize);
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @dataProvider changeFileSize
     */
    public function testChangeFileSizeSize($fileSize, $newFileSize)
    {
        $fileObject = $this->getFileObject();
        $fileObject->ftruncate(0);
        $string = str_repeat('A', $fileSize);
        $fileObject->fwrite($string);
        $fileObject->makeFileLonger($newFileSize);
        $expected = $newFileSize;
        $fileObject->fseekWithCheck(0, SEEK_END);
        $actual = $fileObject->ftell();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @dataProvider changeFileSize
     */
    public function testChangeFileSizeSizeWithSmallBuffer($fileSize, $newFileSize)
    {
        $fileObject = $this->getFileObject();
        $fileObject->setMaxBufferSize(3);
        $fileObject->ftruncate(0);
        $string = str_repeat('A', $fileSize);
        $fileObject->fwrite($string);
        $fileObject->makeFileLonger($newFileSize);
        $expected = $newFileSize;
        $fileObject->fseekWithCheck(0, SEEK_END);
        $actual = $fileObject->ftell();
        $this->assertEquals($expected, $actual);
    }

    public function testChangeFileSizeWrong()
    {
        $fileObject = $this->getFileObject();
        $fileObject->setMaxBufferSize(3);
        $fileObject->ftruncate(0);
        $string = str_repeat('A', 10);
        $fileObject->fwrite($string);
        $this->assertFalse($fileObject->makeFileLonger(5));
        $expected = 10;
        $fileObject->fseekWithCheck(0, SEEK_END);
        $actual = $fileObject->ftell();
        $this->assertEquals($expected, $actual);
    }

}
