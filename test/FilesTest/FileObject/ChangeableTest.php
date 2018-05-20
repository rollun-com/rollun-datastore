<?php

namespace rollun\test\files\FileObject;

class ChangeableTest extends AbstractTest
{

    public function moveSubStrProvider()
    {
        //$charPosFrom, $newCharPos, $string, $expected
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
     *
     * @param int $maxIndex
     * @param int $indexForDelete
     * @dataProvider moveSubStrProvider
     */
    public function testMoveSubStr($charPosFrom, $newCharPos, $string, $expected)
    {
        $fileObject = $this->getFileObject();
        $fileObject->lock(LOCK_EX);
        $this->writeStringsToFile($fileObject, [$string]);
        $fileObject->moveSubStr($charPosFrom, $newCharPos);
        $fileObject->fseekWithCheck(0, SEEK_END);
        $fileSize = $fileObject->ftell();
        $fileObject->fseek(0);
        $stringAfterMove = $fileObject->fread($fileSize);

        $fileObject->unlock();
        unset($fileObject);
        $actual = rtrim($stringAfterMove, "\n");
        $this->assertEquals($expected, $actual);
    }

    public function insertStringProvider()
    {
        //$strings, $insertedString, $beforeLinePos, $expected
        return array(
            //[['012345'], 'ABC', 1, "012345\nABC"],
            [['012345'], 'ABC', 0, "ABC\n012345"],
            [['012345', '543210'], 'ABC', 1, "012345\nABC\n543210"],
            [['012345', '543210'], 'ABC', 0, "ABC\n012345\n543210"],
        );
    }

    /**
     *
     * @param int $maxIndex
     * @param int $indexForDelete
     * @dataProvider insertStringProvider
     */
    public function testInsertString($strings, $insertedString, $beforeLinePos, $expected)
    {
        $fileObject = $this->getFileObject();
        $this->writeStringsToFile($fileObject, $strings);
        $fileObject->insertString($insertedString, $beforeLinePos);
        $fileObject = new \SplFileObject($fileObject->getRealPath(), 'r');
        $fileObject->fseek(0, SEEK_END);
        $fileSize = $fileObject->ftell();
        $fileObject->fseek(0);
        $stringAfterInsert = $fileObject->fread($fileSize);
        unset($fileObject);
        $this->assertEquals($expected, rtrim($stringAfterInsert, "\n"));
    }

    public function rewriteStringProvider()
    {
        //$newStrings, $inLinePos
        return array(
            ["012345", 1],
            ["", 1],
            ["012345", 0],
            ["", 0],
            ["012345", 3],
            ["", 3],
            ["012345", 5],
            ["", 5],
                //["012345", 6],
        );
    }

    /**
     *
     * @dataProvider rewriteStringProvider
     */
    public function testRewriteString($newString, $inLinePos)
    {
        $fileObject = $this->getFileObject();
        $strings = [
            'aaa',
            'bbbb',
            'cc',
            '',
            "d\nd"
        ];
        $this->writeStringsToFile($fileObject, $strings);
        $fileObject->rewriteString($newString, $inLinePos);
        $fileObject = new \SplFileObject($fileObject->getRealPath(), 'r');
        if ($inLinePos === 0) {
            $fileObject->rewind();
        } else {
            $fileObject->seek($inLinePos - 1);
            $fileObject->current();
        }
        $actual = $fileObject->fgets();
        $expected = rtrim($newString, "\r\n") . "\n";
        last - != \n
        $this->assertEquals($expected, $actual);
    }

}
