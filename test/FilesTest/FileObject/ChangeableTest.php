<?php

namespace rollun\test\files\FileObject;

use rollun\test\files\FileObject\FileObjectAbstractTest;

class ChangeableTest extends FileObjectAbstractTest
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

    protected function moveForward($charPosFrom, $newCharPos)
    {
        $fileSize = $this->getFileSize();
        $changes = $this->changeFileSize($fileSize + $newCharPos - $charPosFrom);
        $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $fileSize ? $fileSize - $charPosFrom : $this->getMaxBufferSize();
        $charPosForRead = $fileSize - $bufferSize;
        $charPosForWrite = $fileSize + $newCharPos - $charPosFrom - $bufferSize;
        while ($bufferSize > 0) {
            $this->fseekWithCheck($charPosForRead);
            $buffer = $this->fread($bufferSize);
            $this->fseekWithCheck($charPosForWrite);
            $this->fwriteWithCheck($buffer);
            $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $charPosForRead ? $charPosForRead - $charPosFrom : $this->getMaxBufferSize();
            $charPosForRead = $charPosForRead - $bufferSize;
            $charPosForWrite = $charPosForWrite - $bufferSize;
        }
        $this->fflush();
    }

    protected function moveBackward($charPosFrom, $newCharPos)
    {
        $fileSize = $this->getFileSize();
        $this->fseekWithCheck($charPosFrom);
        while ($charPosFrom < $fileSize) {
            $this->fseekWithCheck($charPosFrom);
            $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $fileSize ? $fileSize - $charPosFrom : $this->getMaxBufferSize();
            $buffer = $this->fread($bufferSize);
            $charPosFrom = $this->ftell();
            $this->fseekWithCheck($newCharPos);
            $this->fwriteWithCheck($buffer);
            $newCharPos = $this->ftell();
        }
        $this->fflush();
        $this->changeFileSize($newCharPos);
    }

    /**
     *
     * @param int $newFileSize
     * @param string $placeholderChar if $newFileSize > $this->fileeSithe()
     * @param int $oldFileSize - do not set this fild!
     * @return int
     * @throws \RuntimeException
     */
    protected function changeFileSize($newFileSize, $placeholderChar = ' ')
    {
        $fileSize = $this->getFileSize();
        if ($newFileSize === $fileSize) {
            return 0;
        }

        if ($newFileSize < $fileSize) {
            $success = $this->ftruncate($newFileSize);
            if (!$success) {
                throw new \RuntimeException("Error changeFileSize to $newFileSize bytes \n in file: \n" . $this->getRealPath());
            }
            return $newFileSize - $fileSize;
        }

        $addQuantity = $this->getMaxBufferSize() < ($newFileSize - $fileSize) ?
                $this->getMaxBufferSize() :
                $newFileSize - $fileSize;
        $string = str_repeat($placeholderChar, $addQuantity);
        $this->fseekWithCheck(0, SEEK_END);
        $this->fwriteWithCheck($string);
        $currentFileSize = $this->getFileSize();
        if ($currentFileSize == $fileSize) {
            throw new \RuntimeException("Error changeFileSize to $newFileSize bytes \n in file: \n" . $this->getRealPath());
        }
        if ($currentFileSize !== $newFileSize) {
            $this->changeFileSize($newFileSize, $placeholderChar);
        }
        return $this->getFileSize() - $fileSize;
    }

}
