<?php

namespace rollun\datastore\Csv;

class CsvFileObject extends \SplFileObject
{

    const LOCK_TRIES_TIMEOUT = 50; //in ms
    const MAX_LOCK_TRIES = 40;

    /**
     *
     * @param string $filename
     * @param string $rwMode see 'mode' in http://php.net/manual/en/function.fopen.php
     */
    public function __construct($filename)
    {
        parent::__construct($filename, 'c+');
        $this->setFlags(self::READ_CSV | self::READ_AHEAD | self::SKIP_EMPTY | self::DROP_NEW_LINE);
    }

    public function __destruct()
    {
        $this->unlock();
    }

    /**
     *
     * @param int  $lockMode LOCK_SH or LOCK_EX
     * @param type $maxTries
     * @param type $timeout
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
        $columns = parent::current();
        $this->unlock();
        return $columns;
    }

    public function rewind()
    {
        parent::rewind();
        parent::current();
        parent::next();
    }

    public function key()
    {
        if (parent::key() === 0) {
            parent::current();
            parent::next();
        }
        return parent::key();
    }

    public function next()
    {
        if (parent::key() === 0) {
            parent::current();
            parent::next();
        }
        parent::next();
    }

    public function current()
    {
        if (parent::key() === 0) {
            parent::current();
            parent::next();
        }

        $row = parent::current();
        if ([null] === $row) {
            return null;
        }
        return $row;
    }

    public function valid()
    {
        if (parent::key() === 0) {
            parent::current();
            parent::next();
        }
        if (!parent::valid()) {
            return false;
        }
        $current = parent::current();
        return $current <> [null];
    }

    public function deleteRow($linePos)
    {

        if ($linePos === 0) {
            throw new \InvalidArgumentException('Can not delete header of CSV file.');
        }
        $this->lock(LOCK_EX);

        $startPosForRewrite = $linePos;
        parent::seek($startPosForRewrite - 1);

        $startForRewriteInBytes = $this->ftell();
        $this->fseek($startForRewriteInBytes);
        $csv1 = parent::current();

        $startPosForReadInBytes = $this->ftell();
        $this->fseek($startPosForReadInBytes);
        $csv2 = parent::current();

        $truncatePos = $startPosForRewrite;

        while (!$this->eof()) {
            $this->fseek($startPosForReadInBytes);
            parent::current();

            $buffer = [];
            while ($this->valid() && count($buffer) < 10000) {
                $buffer[] = $this->current();
                $truncatePos++;
                $startPosForReadInBytes = $this->ftell();
                $this->next();
            }
            $startForRewriteInBytes = $this->updateRows($buffer, $startForRewriteInBytes);
            $this->fseek($startPosForReadInBytes);
            parent::current();
        }
        $this->fflush();

        $this->seek($truncatePos - 1);
        $this->current();
        $truncatePosInByte = $this->ftell();

        $this->ftruncate($truncatePosInByte);
        $this->unlock();
    }

    protected function updateRows(array $buffer, $startForRewriteInBytes)
    {
        $this->fseek($startForRewriteInBytes);
        foreach ($buffer as $key => $line) {

            $this->fputcsv($line);
            $startForRewriteInBytes = $this->ftell();
        }

        return $startForRewriteInBytes;
    }

}
