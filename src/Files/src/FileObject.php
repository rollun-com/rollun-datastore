<?php

namespace rollun\files;

use Ajgl\Csv\Rfc;

class FileObject extends Rfc\Spl\SplFileObject
{

    const LOCK_TRIES_TIMEOUT = 50; //in ms
    const MAX_LOCK_TRIES = 40;

    /**
     * Buffer size in lines for coping operation
     */
    const BUFFER_SIZE = 100;  //i

    /**
     *
     * @param string $filename
     * @param string $rwMode see 'mode' in http://php.net/manual/en/function.fopen.php
     */
    public function __construct($filename)
    {
        parent::__construct($filename, 'c+');
        $this->setFlags(\SplFileObject::READ_AHEAD); //| \SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD |\SplFileObject: \SplFileObject::SKIP_EMPTY
        //$this->setCsvControl(',', '"', '"');
    }

    public function __destruct()
    {
        $this->unlock();
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

    public function deleteRow($linePos)
    {

//$this->csvModeOff();
        $this->lock(LOCK_EX);
        $flags = $this->getFlags();
        $this->setFlags(0);
        if ($linePos === 0) {
            $this->rewind();
            $charPosTo = 0;
        } else {
            parent::seek($linePos - 1);
            parent::current();
            $charPosTo = $this->ftell();
            parent::next();
        }

        parent::current();
        $charPosFrom = $this->ftell();




        $truncatePos = $this->moveRows($charPosFrom, $charPosTo);

        $this->fflush();
        $this->ftruncate($truncatePos);
        $this->setFlags($flags);
        $this->unlock();
    }

    protected function moveRows($charPosFrom, $charPosTo)
    {
        $this->fseek($charPosFrom);
        parent::current();
        while ($this->valid()) {
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
                $this->fwrite($line);  //$this->fputcsv($line); in csv mode
                $charPosTo = $this->ftell();
            }

            $this->fseek($charPosFrom);
            $current = parent::current();
        }
        return $charPosTo;
    }

}
