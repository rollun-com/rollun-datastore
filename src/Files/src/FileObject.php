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
    const BUFFER_SIZE = 10;  //i

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
        $this->lock(LOCK_EX);
        $flags = $this->getFlags();
        $this->setFlags(0);
        if ($linePos === 0) {
            $this->rewind();
            $newCharPos = 0;
        } else {
            parent::seek($linePos - 1);
            parent::current();
            $newCharPos = $this->ftell();
            parent::next();
        }
        parent::current();
        $charPosFrom = $this->ftell();
        $this->moveBackward($charPosFrom, $newCharPos);
        $this->setFlags($flags);
        $this->unlock();
    }

    /**
     *
     * @param string $string string for insert. \n will be added if not exist.
     * @param type $beforeLinePos zero based line number. null for uppend to the end of file.
     */
    public function insertString($insertedString, $beforeLinePos = null)
    {
        $insertedString = rtrim($insertedString, "\r\n") . "\n";
        if (is_null($beforeLinePos)) {
            $this->fseek(0, SEEK_END);
            $this->fwrite($insertedString);
            return;
        }

        $this->lock(LOCK_EX);
        $flags = $this->getFlags();
        $this->setFlags(0);
        if ($beforeLinePos === 0) {
            $charPosFrom = 0;
        } else {
            parent::seek($beforeLinePos - 1);
            parent::current();
            $charPosFrom = $this->ftell();
        }
        $newCharPos = $charPosFrom + strlen($insertedString);
        $this->fseek(0);
        $this->moveForward($charPosFrom, $newCharPos);
        $this->fseek($charPosFrom);
        $this->fwrite($insertedString);
        $this->setFlags($flags);
        $this->unlock();
    }

    /**
     * Move last part of file (from $charPosFrom to EOF) to $newCharPos
     *
     * @param int $charPosFrom
     * @param int $newCharPos
     * @return int truncate position
     */
    public function moveSubStr($charPosFrom, $newCharPos)
    {
        $this->lock(LOCK_EX);
        $flags = $this->getFlags();
        $this->setFlags(0);
        switch (true) {
            case $charPosFrom < $newCharPos:
                $this->moveForward($charPosFrom, $newCharPos);
                break;
            case $charPosFrom > $newCharPos:
                $this->moveBackward($charPosFrom, $newCharPos);
                break;
            default:
                break;
        }
        $this->setFlags($flags);
        $this->unlock();
    }

    protected function moveForward($charPosFrom, $newCharPos)
    {
        $this->fseek(0, SEEK_END);
        $fileSize = $this->ftell();
        $bufferSize = ($charPosFrom + static::BUFFER_SIZE) > $fileSize ? $fileSize - $charPosFrom : static::BUFFER_SIZE;
        $charPosForRead = $fileSize - $bufferSize;
        $charPosForWrite = $fileSize + $newCharPos - $charPosFrom - $bufferSize;
        while ($bufferSize > 0) {
            if ($this->fseek($charPosForRead) == -1) {
                throw new \InvalidArgumentException('$charPosForRead =' . $charPosForRead . " in file: \n" . $this->getRealPath());
            }
            $buffer = $this->fread($bufferSize);
            if ($this->fseek($charPosForWrite) == -1) {
                throw new \InvalidArgumentException('$charPosForWrite =' . $charPosForWrite . " in file: \n" . $this->getRealPath());
            }
            $this->fwrite($buffer);
            $bufferSize = ($charPosFrom + static::BUFFER_SIZE) > $charPosForRead ? $charPosForRead - $charPosFrom : static::BUFFER_SIZE;
            $charPosForRead = $charPosForRead - $bufferSize;
            $charPosForWrite = $charPosForWrite - $bufferSize;
        }
        $this->fflush();
    }

    protected function moveBackward($charPosFrom, $newCharPos)
    {
        $this->fseek(0, SEEK_END);
        $fileSize = $this->ftell();
        $this->fseek($charPosFrom);
        while ($charPosFrom < $fileSize) {
            if ($this->fseek($charPosFrom) == -1) {
                throw new \InvalidArgumentException('$charPosFrom =' . $charPosFrom . " in file: \n" . $this->getRealPath());
            }
            $bufferSize = ($charPosFrom + static::BUFFER_SIZE) > $fileSize ? $fileSize - $charPosFrom : static::BUFFER_SIZE;
            $buffer = $this->fread($bufferSize);
            $charPosFrom = $this->ftell();
            if ($this->fseek($newCharPos) == -1) {
                throw new \InvalidArgumentException('$newCharPos =' . $newCharPos . " in file: \n" . $this->getRealPath());
            }
            $this->fwrite($buffer);
            $newCharPos = $this->ftell();
        }
        $this->fflush();
        $this->ftruncate($newCharPos);
    }

}
