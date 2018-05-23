<?php

namespace rollun\files;

use rollun\files\FileObject;

class CsvFileObject
{

    /**
     *
     * @var FileObject
     */
    protected $fileObject;

    /**
     *
     * @var array
     */
    protected $columns;

    public static function createNewCsvFile(string $filename, array $columnsNames)
    {
        if (is_readable($filename)) {
            throw new \InvalidArgumentException(
            "There is readable file: " . $filename
            );
        }
        $fileObject = new FileObject($filename);
        $fileObject->fputcsv($columnsNames);
    }

    public function __construct(string $filename)
    {
        if (!is_readable($filename)) {
            throw new \InvalidArgumentException(
            "There is not readable file: " . $filename
            );
        }
        $this->fileObject = new FileObject($filename);
        $this->fileObject->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::READ_CSV); //| \SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD |\SplFileObject: \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV
        $this->getColumns();
    }

    public function getColumns()
    {
        if (empty($this->columns)) {
            $this->fileObject->lock(LOCK_SH);
            $this->fileObject->rewind();
            $current = $this->fileObject->current();
            $this->fileObject->unlock();
            if (!is_array($current)) {
                throw new \InvalidArgumentException(
                "There is not colums names in file: " . $this->fileObject->getRealPath()
                );
            }
            $this->columns = $current;
        }
        return $this->columns;
    }

    public function getRow($oneBasedIndex)
    {
        if ($oneBasedIndex === 0) {
            throw new \InvalidArgumentException(
            "\$oneBasedIndex must be bigger then 0 \n in file: " . $this->fileObject->getRealPath()
            );
        }
        $this->fileObject->lock(LOCK_SH);
        $stringsCount = $this->fileObject->getStringsCount();
        if ($stringsCount < $oneBasedIndex) {
            throw new \InvalidArgumentException(
            "\$oneBasedIndex = $oneBasedIndex .  bigger then strings count = $stringsCount \n in file: " . $this->fileObject->getRealPath()
            );
        }
        $this->fileObject->seek($oneBasedIndex);
        $row = $this->fileObject->current();
        $this->fileObject->unlock();
        return $row;
    }

}
