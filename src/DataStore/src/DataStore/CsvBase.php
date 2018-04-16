<?php

namespace rollun\datastore\DataStore;

use rollun\datastore\DataStore\DataStoreAbstract;
use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\Interfaces\DataSourceInterface;
use rollun\datastore\DataStore\Iterators\CsvIterator;
use Xiag\Rql\Parser\Query;
use rollun\installer\Command;

class CsvBase extends DataStoreAbstract implements DataSourceInterface
{

    /**
     * Max size of the file in bytes
     */
    const MAX_FILE_SIZE_FOR_CACHE = 8388608;
    const DEFAULT_CSV_DELIMETER = ';';

    /**
     *
     * @var \SplFileObject
     */
    protected $splFileObject;

    /**
     *
     * @var string
     */
    public $filename;

    /**
     * Column headings
     * @var mixed array
     */
    public $columns;
    public $csvDelimiter;

    /**
     * Csv constructor. If file with this name doesn't exist attempts find it in document root directory
     *
     * @param string $filename
     * @param string $csvDelimiter - csv field delimiter
     * @throws \rollun\datastore\DataStore\DataStoreException
     */
    public function __construct($filename, $csvDelimiter = self::DEFAULT_CSV_DELIMETER)
    {
        $this->setFilename($filename);
        $this->csvDelimiter = $csvDelimiter;
        $this->setColumns();
    }

    public function getIdentifier()
    {
        return $this->columns[0];
    }

    protected function setFilename($filename)
    {
        if (is_file($filename)) {
            $this->filename = $filename;
        } else {
            $dataDir = Command::getDataDir();
            $filenameInSDataDir = realpath($dataDir . DIRECTORY_SEPARATOR . trim($filename, DIRECTORY_SEPARATOR));
            if (is_file($filenameInSDataDir)) {
                $this->filename = $filenameInSDataDir;
            } else {
                throw new \InvalidArgumentException('The specified source file does not exist.' . PHP_EOL . "Filename is $filename");
            }
        }
    }

    protected function setSplFileObject()
    {
        if (!isset($this->splFileObject)) {
            $this->splFileObject = new \SplFileObject($this->filename);
            $this->splFileObject->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
            $this->splFileObject->setCsvControl($this->csvDelimiter);
        }
        return $this->splFileObject;
    }

    protected function unsetSplFileObject()
    {
        $this->unlock();
        unset($this->splFileObject);
    }

    public function read($id = null)
    {
        $this->setSplFileObject();
        foreach ($this->splFileObject as $key => $row) {
            if ($key <> 0 && $row[0] == $id) {
                return $this->getTrueRow($row);
            }
        }
        $this->unsetSplFileObject();
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new CsvIterator($this, $this->filename);
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function create($itemData, $rewriteIfExist = false)
    {
//        $identifier = $this->getIdentifier();
//        switch (true) {
//            case (!isset($itemData[$identifier])):
//                // There isn't item with identifier in the data set; creates a new item
//                $item = $this->createNewItem($itemData);
//                $item[$identifier] = $this->generatePrimaryKey();
//                break;
//            case (!$rewriteIfExist && !is_null($this->read($itemData[$identifier]))):
//                throw new DataStoreException('Item is already exist with "id" =  ' . $itemData[$identifier]);
//                break;
//            default:
//                // updates an existing item
//                $id = $itemData[$identifier];
//                $this->checkIdentifierType($id);
//                $item = $this->createNewItem($itemData);
//                break;
//        }
//        $this->flush($item);
//        return $item;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function update($itemData, $createIfAbsent = false)
    {
//        $identifier = $this->getIdentifier();
//        if (!isset($itemData[$identifier])) {
//            throw new DataStoreException('Item must have primary key');
//        }
//        $id = $itemData[$identifier];
//        $this->checkIdentifierType($id);
//        $item = $this->read($id);
//
//        switch (true) {
//            case (is_null($item) && !$createIfAbsent):
//                $errorMsg = sprintf('Can\'t update item with "id" = %s: item does not exist.', $id);
//                throw new DataStoreException($errorMsg);
//            case (is_null($item) && $createIfAbsent):
//                // new item
//                $item = $this->createNewItem($itemData);
//                break;
//        }
//        foreach ($item as $key => &$value) {
//            if (isset($itemData[$key])) {
//                $item[$key] = $itemData[$key];
//            }
//        }
//        $this->flush($item);
//        return $item;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function delete($id)
    {
//        $this->checkIdentifierType($id);
//        // If item with specified id was found flushs file without it
//        $item = $this->read($id);
//        if (!is_null($item)) {
//            $this->flush($item, true);
//            return $item;
//        }
//        // Else do nothing
        return null;
    }

//
//    /**
//     * {@inheritdoc}
//     *
//     * {@inheritdoc}
//     */
//    public function deleteAll()
//    {
//        // Count rows
//        $count = $this->count();
//        $tmpFile = tempnam("/tmp", uniqid() . '.tmp');
//        $tempHandler = fopen($tmpFile, 'w');
//        // Write the headings only and right away closes file
//        fputcsv($tempHandler, $this->columns, $this->csvDelimiter);
//        fclose($tempHandler);
//        // Changes the original file to a temporary one.
//        if (!rename($tmpFile, $this->filename)) {
//            throw new DataStoreException("Failed to write the results to a file.");
//        }
//        return $count;
//    }
//
//    /**
//     * Flushes all changes to temporary file which then will change the original one
//     *
//     * @param $item
//     * @param bool|false $delete
//     * @throws \rollun\datastore\DataStore\DataStoreException
//     */
//    protected function flush($item, $delete = false)
//    {
//        // Create and open temporary file for writing
//        $tmpFile = tempnam(sys_get_temp_dir(), uniqid() . '.tmp');
//        $tempHandler = fopen($tmpFile, 'w');
//        // Write headings
//        fputcsv($tempHandler, $this->columns, $this->csvDelimiter);
//
//        $identifier = $this->getIdentifier();
//        $inserted = false;
//        foreach ($this as $index => $row) {
//            // Check an identifier; if equals and it doesn't need to delete - inserts new item
//            if ($item[$identifier] == $row[$identifier]) {
//                if (!$delete) {
//                    $this->writeRow($tempHandler, $item);
//                }
//                // anyway marks row as inserted
//                $inserted = true;
//            } else {
//                // Just it inserts row from source-file (copying)
//                $this->writeRow($tempHandler, $row);
//            }
//        }
//        // If the same item was not found and changed inserts the new item as the last row in the file
//        if (!$inserted) {
//            $this->writeRow($tempHandler, $item);
//        }
//        fclose($tempHandler);
//        // Copies the original file to a temporary one.
//        if (!copy($tmpFile, $this->filename)) {
//            unlink($tmpFile);
//            throw new DataStoreException("Failed to write the results to a file.");
//        }
//        unlink($tmpFile);
//    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function getAll()
    {
        if (filesize($this->filename) <= static::MAX_FILE_SIZE_FOR_CACHE) {
            $return = $this->query(new Query);
        } else {
            $return = $this->getIterator();
        }
        return $return;
    }

//
//    /**
//     * Creates a new item, combines data with the column headings
//     * @param $itemData
//     * @return array
//     */
//    protected function createNewItem($itemData)
//    {
//        $item = array_flip($this->columns);
//        foreach ($item as $key => &$value) {
//            if (isset($itemData[$key])) {
//                $item[$key] = $itemData[$key];
//            } else {
//                $item[$key] = null;
//            }
//        }
//        return $item;
//    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function count()
    {
        $count = 0;
        foreach ($this as $item) {
            $count++;
        }
        return $count;
    }

    /**
     * Returns the associative array with the column headings;
     * also checks and sanitize empty string and null value and converts type for the numeric fields
     * @param $row
     * @return array|null
     */
    public function getTrueRow($row)
    {
        if ($row) {
            array_walk($row, function(&$item, $key) {
                if ('' === $item) {
                    $item = null;
                }
                if ($item === '""') {
                    $item = '';
                }
                //fixed bug with first zero string
                $isZeroFirstString = /*false;//*/strlen($item) > 1 && substr($item, 0, 1) == "0";
                if (is_numeric($item) && !$isZeroFirstString) {
                    if (intval($item) == $item) {
                        $item = intval($item);
                    } else {
                        $item = floatval($item);
                    }
                }
            });
            return array_combine($this->columns, $row);
        }
        return null;
    }

    /**
     * Writes the row in the csv-format
     * also converts empty string to string of two quotes
     * It's necessary to distinguish the difference between empty string and null value: both are writen as empty value
     * @param $fHandler
     * @param $row
     */
    public function writeRow($fHandler, $row)
    {
        $this->setSplFileObject();
        $this->lock(LOCK_EX);

        array_walk($row, function(&$item, $key) {
            switch (true) {
                case ('' === $item):
                    $item = '""';
                    break;
                case (true === $item):
                    $item = 1;
                    break;
                case (false === $item):
                    $item = 0;
                    break;
            }
        });
        $this->unsetSplFileObject();
    }

    /**
     * Generates an unique identifier
     * @return string
     */
    protected function generatePrimaryKey()
    {
        return uniqid();
    }

}
