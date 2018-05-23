<?php

namespace rollun\test\files;

use rollun\files\FileObject;
use rollun\files\FileManager;
use rollun\installer\Command;

abstract class FilesAbstractTest extends \PHPUnit_Framework_TestCase
{

    public function tearDown()
    {
        $fullFilename = $this->makeFullFileName();
        unlink($fullFilename);
    }

    protected function makeDirName()
    {
        $fileManager = new FileManager;
        $dataDir = Command::getDataDir();
        $pathArray = explode('\\', strtolower(__NAMESPACE__));
        array_shift($pathArray);
        $subDir = implode('/', $pathArray);
        $dirName = $fileManager->joinPath($dataDir, $subDir);
        return $dirName;
    }

    protected function makeFileName()
    {
        $name = pathinfo($name = get_class($this) . '.txt')['basename'];
        return $name;
    }

    protected function makeFullFileName()
    {
        $fileManager = new FileManager;
        $dirName = $this->makeDirName();
        $fileManager->createDir($dirName);
        $filename = $this->makeFileName();
        $fullFilename = $fileManager->joinPath($dirName, $filename);
        return $fullFilename;
    }

}
