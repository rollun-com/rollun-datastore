<?php

namespace rollun\test\files\FileObject;

use rollun\test\files\FilesAbstractTest;
use rollun\files\FileObject;
use rollun\files\FileManager;
use rollun\installer\Command;

abstract class FileObjectAbstractTest extends FilesAbstractTest
{

    protected function getFileObject($flags = 0)
    {
        $fileManager = new FileManager;
        $dirName = $this->makeDirName();
        $fileManager->createDir($dirName);
        $filename = $this->makeFileName();
        $fullFilename = $fileManager->joinPath($dirName, $filename);
        $stream = $fileManager->createAndOpenFile($fullFilename, true);
        $fileManager->closeStream($stream);
        $fileObject = new FileObject($fullFilename);
        $fileObject->setFlags($flags);
        return $fileObject;
    }

    protected function writeStringsToFile(\SplFileObject $fileObject, $stringsArray)
    {
        $fileObject->ftruncate(0);
        foreach ($stringsArray as $string) {
            $fileObject->fwrite(rtrim($string, "\n\r") . "\n");
            $fileObject->fflush();
        }
        $fileObject->fseek(0, SEEK_END);
        $fileSize = $fileObject->ftell();
        //$fileObject->ftruncate($fileSize - 1); //delete last EOL
        $fileObject->fseek(0);
    }

}
