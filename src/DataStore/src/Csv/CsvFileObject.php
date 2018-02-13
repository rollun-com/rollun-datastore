<?php

namespace rollun\datastore\Csv;

class CsvFileObject extends \SplFileObject
{

    const LOCK_TRIES_TIMEOUT = 50; //in ms
    const MAX_LOCK_TRIES = 40;

    /**
     * Buffer size in lines for coping operation
     */
    const BUFFER_SIZE = 1000;  //i

    /**
     * csv mode on - true, csv mode off - false
     *
     * @var bool
     */
    protected $prevCsvMode = null;

    /**
     *
     * @param string $filename
     * @param string $rwMode see 'mode' in http://php.net/manual/en/function.fopen.php
     */
    public function __construct($filename)
    {
        parent::__construct($filename, 'c+');
        $this->csvModeOn();
    }

    public function __destruct()
    {
        $this->unlock();
    }

    public function csvModeOn()
    {
        $this->prevCsvMode = $this->isCsvMode();
        $this->setFlags(self::READ_CSV | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
    }

    public function csvModeOff()
    {
        $this->prevCsvMode = $this->isCsvMode();
        $this->setFlags(0 | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
    }

    public function isCsvMode()
    {
        $flags = $this->getFlags();
        return (bool) ($flags & self::READ_CSV);
    }

    public function restorePrevCsvMode()
    {
        switch (true) {
            case $this->prevCsvMode === true:
                $this->csvModeOn();
                break;
            case $this->prevCsvMode === false:
                $this->csvModeOff();
                break;
            default:
                throw new \RuntimeException('Can not restore CSV mode');
        }
        $this->prevCsvMode = null;
    }

    /**
     *
     * @param int  $lockMode LOCK_SH or LOCK_EX
     * @param type $maxTries
     * @param type $timeout in ms
     * @throws DataStoreException
     */
    public function lock($lockMode, $maxTries = null, $lockTriesTimeout = null)
    {
        $maxTries = $maxTries ?? static::MAX_LOCK_TRIES;
        $lockTriesTimeout = $lockTriesTimeout ?? static::LOCK_TRIES_TIMEOUT;

        if ($lockMode <> LOCK_SH && $lockMode <> LOCK_EX) {
            throw new \InvalidArgumentException('$lockMode must be LOCK_SH or LOCK_EX');
        }

        $count = 0;
        while (!$this->flock($lockMode | LOCK_NB, $wouldblock)) {
            if (!$wouldblock) {
                throw new DataStoreException('There is a problem with file: ' . $this->filename);
            }
            if ($count++ > $maxTries) {
                throw new DataStoreException('Can not lock the file: ' . $this->filename);
            }
            usleep($lockTriesTimeout);
        }
    }

    public function unlock()
    {
        return $this->flock(LOCK_UN);
    }

    public function getColumns()
    {
        $this->lock(LOCK_SH);
        parent::rewind();
        $current = parent::current();
        $columns = is_array($current) ? $current : trim($current);
        $this->unlock();
        return $columns;
    }

    public function rewind()
    {
        parent::rewind();
        parent::current();
        if ($this->isCsvMode()) {
            parent::next();
            parent::current();
        }
    }

    public function key()
    {
        if (parent::key() === 0 && $this->isCsvMode()) {
            parent::current();
            parent::next();
        }
        return parent::key();
    }

    public function next()
    {
        if (parent::key() === 0 && $this->isCsvMode()) {
            parent::current();
            parent::next();
        }
        parent::next();
    }

    public function current()
    {
        if (parent::key() === 0 && $this->isCsvMode()) {
            parent::current();
            parent::next();
        }

        $row = parent::current();
        if ([null] === $row) {
            return null;
        }
        return is_array($row) ? $row : $row;
    }

    public function valid()
    {
        if (parent::key() === 0 && $this->isCsvMode()) {
            parent::current();
            parent::next();
        }
        if (!parent::valid()) {
            return false;
        }
        $current = parent::current();
        return $current <> [null] && $current <> null;
    }

    public function deleteRow($linePos)
    {
        if ($linePos === 0) {
            throw new \InvalidArgumentException('Can not delete header of CSV file.');
        }
        $this->csvModeOff();
        $this->lock(LOCK_EX);

        parent::seek($linePos - 1); // I do not know why '-1'
        $charPosTo = $this->ftell();
        $this->fseek($charPosTo);
        parent::current();
        $charPosFrom = $this->ftell();

        $truncatePos = $this->moveRows($charPosFrom, $charPosTo);

        $this->fflush();
        $this->ftruncate($truncatePos);
        $this->restorePrevCsvMode();
        $this->unlock();
    }

    protected function moveRows($charPosFrom, $charPosTo)
    {
        $this->fseek($charPosFrom);
        while (!$this->eof()) {
            $this->fseek($charPosFrom);
            parent::current();

            $buffer = [];
            while ($this->valid() && count($buffer) <= static::BUFFER_SIZE) {
                $buffer[] = $this->current();
                $charPosFrom = $this->ftell();
                $this->next();
            }

            $this->fseek($charPosTo);
            foreach ($buffer as $key => $line) {
                $this->fwrite($line . PHP_EOL);  //$this->fputcsv($line); in csv mode
                $charPosTo = $this->ftell();
            }

            $this->fseek($charPosFrom);
            parent::current();
            return $charPosTo;
        }
    }

}
