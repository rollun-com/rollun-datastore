<?php

namespace rollun\test\files\CsvFileObject;

use rollun\files\FileObject;
use rollun\files\CsvFileObject;
use rollun\files\FileManager;
use rollun\test\files\FilesAbstractTest;

abstract class CsvFileObjectAbstractTest extends FilesAbstractTest
{

    protected function getCsvFileObject(string $stringInFile)
    {
        $fileManager = new FileManager;
        $fullFilename = $this->makeFullFileName();
        $stream = $fileManager->createAndOpenFile($fullFilename, true);
        $fileManager->closeStream($stream);
        file_put_contents($fullFilename, $stringInFile);
        $csvFileObject = new CsvFileObject($fullFilename);
        return $csvFileObject;
    }

}
