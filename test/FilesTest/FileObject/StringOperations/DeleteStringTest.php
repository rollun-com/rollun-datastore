<?php

namespace rollun\test\files\FileObject\StringOperations;

use rollun\test\files\FileObject\StringOperations\StringOperationsAbstractTest;

class DeleteStringTest extends StringOperationsAbstractTest
{

    public function deleteStringProvider()
    {
        //$stringsArray, $stringNumber
        $stringsArray = $this->specialStringsPrvider();
        $stringsArrays = $this->cyclicShifts($stringsArray);
        $stringNumbersArray = $this->stringsNumbersProvider($stringsArray);

        foreach ($stringsArrays as $stringsArray) {
            foreach ($stringNumbersArray as $stringNumber) {
                $providedData[] = [$stringsArray, $stringNumber];
            }
        }
        return $providedData;
    }

    /**
     *
     * @dataProvider deleteStringProvider
     */
    public function testDeleteString($stringsArray, $stringNumber)
    {
        $fileObject = $this->getFileObject();
        $this->writeStringsToFile($fileObject, $stringsArray);
        $fileObject->deleteString($stringNumber);
        $fileSize = $fileObject->getFileSize();
        $fileObject->fseekWithCheck(0);
        $actualString = $fileObject->fread($fileSize);
        unset($stringsArray[$stringNumber]);
        $expectedString = implode("\n", $stringsArray) . "\n";

        $this->assertEquals($expectedString, $actualString);
    }

    public function deleteStringExceptionProvider()
    {
        //$indexForDelete, $stringsArray
        return array(
            [0, ""], [1, ""], [5, ""],
            [1, "1"], [1, "1/n"], [1, "/n"], [5, "1/n"],
            [10, "0/n1/n2/n3/n4/n5/n6/n7/n8/n9/n"],
        );
    }

    /**
     *
     * @dataProvider deleteStringExceptionProvider
     */
    public function testDeleteStringException($indexForDelete, $stringInFile)
    {
        $fileObject = $this->getFileObject();
        $fileObject->fwriteWithCheck($stringInFile);
        $this->expectException(\Exception::class);
        $fileObject->deleteString($indexForDelete);
    }

    public function eolProvider()
    {
        //$indexForDelete, $stringInFile, $expectedString
        return array(
            [0, "0", ""], [0, "\n", ""], [0, "0\n1", "1"], [1, "0\n1", "0\n"], [0, "0\n", ""],
            [0, "0\n1\n2", "1\n2"], [0, "\n1\n2", "1\n2"], [1, "0\n1\n2", "0\n2"], [2, "0\n1\n2", "0\n1\n"], [0, "0\n1\n", "1\n"],
        );
    }

    /**
     *
     * @dataProvider eolProvider
     */
    public function testEol($indexForDelete, $stringInFile, $expectedString)
    {
        $fileObject = $this->getFileObject();

        $fileObject->fwriteWithCheck($stringInFile);
        $fileObject->deleteString($indexForDelete);
        $fileObject->fseekWithCheck(0);
        $actualString = $fileObject->fread(10);
        $this->assertEquals($expectedString, $actualString);
    }

    public function deleteStringBufferSizeProvider()
    {
        //$maxIndex, $indexForDelete, $maxBufferSize
        return array(
            //smallBuffer
            [10, 0, 5], [10, 1, 5], [10, 4, 5], [10, 5, 5], [10, 6, 5], [10, 10, 5],
            //bigBuffer
            [10, 0, 10], [10, 10, 10], [10, 0, 15], [10, 1, 15], [10, 5, 15], [10, 10, 15],
        );
    }

    /**
     * @dataProvider deleteStringBufferSizeProvider
     */
    public function testDeleteStringBufferSize($maxIndex, $indexForDelete, $maxBufferSize)
    {

        for ($index = 0; $index <= $maxIndex; $index++) {
            $stringArray[] = str_repeat($index, rand(1, 1000)); // rand(1, 100)//1 + $maxIndex - $index   rand(1, 1000)
        }
        $strings = implode("\n", $stringArray) . "\n";
        unset($stringArray[$indexForDelete]);
        $expected = implode("\n", $stringArray) . "\n";

        $fileObject = $this->getFileObject();
        $fileObject->fwrite($strings);
        $fileObject->setMaxBufferSize($maxBufferSize);

        $fileObject->deleteString($indexForDelete);

        $fileSize = $fileObject->getFileSize();
        $fileObject->fseek(0);
        $actual = $fileObject->fread($fileSize);

        $this->assertEquals($expected, $actual);
    }

}
