<?php

namespace rollun\test\files\FileObject;

class ChangeableTest extends AbstractTest
{

    public function deleteStringProvider1()
    {
        //$maxIndex, $indexForDelete
        return array(
            [10, 0, 5],
            [10, 1, 5],
            [10, 4, 5],
            [10, 5, 5],
            [10, 6, 5],
            [10, 10, 5],
            [10, 0, 10],
            [10, 10, 10],
            [10, 0, 15],
            [10, 1, 15],
            [10, 5, 15],
            [10, 10, 15],
        );
    }

    /**
     *
     * @param int $maxIndex
     * @param int $indexForDelete
     * @dataProvider deleteStringProvider1
     */
    public function test1DeleteString($maxIndex, $indexForDelete, $maxBufferSize)
    {

        for ($index = 0; $index <= $maxIndex; $index++) {
            $val = str_repeat($index, rand(1, 1000)); // rand(1, 100)//1 + $maxIndex - $index
            $rows[] = $val;
            if ($index != $indexForDelete) {
                $expectedRows[] = $val . "\n";
            }
        }
        $fileObject = $this->getFileObject();
        $fileObject->setMaxBufferSize($maxBufferSize);
        $this->fillFile($fileObject, $rows);

        $fileObject->deleteString($indexForDelete);
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

    public function deleteStringProvider2()
    {
        //$indexForDelete, $strings, $expected
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
     * @dataProvider deleteStringProvider2
     */
    public function test2DeleteString($indexForDelete, $strings, $expected)
    {
        $fileObject = $this->getFileObject();
        $this->fillFile($fileObject, $strings);
        $fileObject->deleteString($indexForDelete);
        $fileObject = new \SplFileObject($fileObject->getRealPath(), 'r');

        $string = $fileObject->fread(10);
        unset($fileObject);

        $this->assertEquals($expected, $string);
    }

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
            [0, 10, '012345', "012345\n   012345"],
            [0, 3, "012345678", '012012345678'],
            [0, 7, '012345', "012345\n012345"],
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
        $this->fillFile($fileObject, [$string]);
        $fileObject->moveSubStr($charPosFrom, $newCharPos);

        //$fileObject = new \SplFileObject($fileObject->getRealPath(), 'r');
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
            [['012345'], 'ABC', 1, "012345\nABC"],
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
        $this->fillFile($fileObject, $strings);
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
            ["012345", 6],
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
        $this->fillFile($fileObject, $strings);
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
        $this->assertEquals($expected, $actual);
    }

}
