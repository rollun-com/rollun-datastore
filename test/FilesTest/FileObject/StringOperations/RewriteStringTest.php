<?php

namespace rollun\test\files\FileObject\StringOperations;

use rollun\test\files\FileObject\StringOperations\StringOperationsAbstractTest;

class RewriteStringTest extends StringOperationsAbstractTest
{

    public function rewriteStringProvider()
    {
        //$indexForRewrite, $stringForRewrite, $stringInFile, $expectedString
        return array(
            [0, 'rw', 'a', "rw\n"], [0, "rw\n", 'a', "rw\n"], [0, 'rw', "a\n", "rw\n"], [0, "rw\n", "a\n", "rw\n"],
            [0, 'rw', "A\nB\n", "rw\nB\n"], [0, 'rw', "A\nB", "rw\nB"], [1, 'rw', "A\nB\n", "A\nrw\n"], [1, 'rw', "A\nB", "A\nrw\n"],
            [0, '', "A\nB\n", "\nB\n"], [1, "\n", "A\nB", "A\n\n"],
            [0, '', "\nB\n", "\nB\n"], [1, '', "\nB\n", "\n\n"],
        );
    }

    /**
     *
     * @dataProvider rewriteStringProvider
     */
    public function testRewriteString($indexForRewrite, $stringForRewrite, $stringInFile, $expectedString)
    {
        $fileObject = $this->getFileObject();

        $fileObject->fwriteWithCheck($stringInFile);
        $fileObject->rewriteString($stringForRewrite, $indexForRewrite);
        $fileObject->fseekWithCheck(0);
        $actualString = $fileObject->fread(100);
        $this->assertEquals($expectedString, $actualString);
    }

}
