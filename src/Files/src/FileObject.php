<?php

namespace rollun\files;

use Ajgl\Csv\Rfc;

class FileObject extends Rfc\Spl\SplFileObject
{

    const DELAULT_LOCK_TRIES_TIMEOUT = 50; //in ms
    const DELAULT_MAX_LOCK_TRIES = 20 * 10;

    /**
     * Buffer size in  bytes for coping operation
     */
    const DELAULT_MAX_BUFFER_SIZE = 10000000;

    public $lockTriesTimeout;
    public $maxLockTries;
    protected $maxBufferSize;
    protected $lockModesStack;
    protected $prevLockMode;

    /**
     *
     * @param string $filename
     * @param string $rwMode see 'mode' in http://php.net/manual/en/function.fopen.php
     */
    public function __construct($filename)
    {
        parent::__construct($filename, 'c+');
        $this->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::READ_CSV); //| \SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD |\SplFileObject: \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_CSV
        $this->setMaxBufferSize(static::DELAULT_MAX_BUFFER_SIZE);
        $this->lockTriesTimeout = static::DELAULT_LOCK_TRIES_TIMEOUT;
        $this->maxLockTries = static::DELAULT_MAX_LOCK_TRIES;
    }

    public function __destruct()
    {
        $this->unlock();
    }

    public function setMaxBufferSize($maxBufferSize)
    {
        $this->maxBufferSize = $maxBufferSize;
    }

    public function getMaxBufferSize()
    {
        return $this->maxBufferSize;
    }

    /**
     *
     * @param int  $lockMode LOCK_SH or LOCK_EX
     * @param type $maxLockTries
     * @param type $lockTriesTimeout in ms
     */
    public function lock($lockMode, $maxLockTries = null, $lockTriesTimeout = null)
    {
        $maxTries = $maxLockTries ?? $this->maxLockTries;
        $triesTimeout = $lockTriesTimeout ?? $this->lockTriesTimeout;

        if ($lockMode <> LOCK_SH && $lockMode <> LOCK_EX) {
            throw new \InvalidArgumentException('$lockMode must be LOCK_SH or LOCK_EX');
        }

        $count = 0;
        while (!$this->flock($lockMode | LOCK_NB, $wouldblock)) {
            if (!$wouldblock) {
                throw new \RuntimeException('There is a problem with file: ' . $this->getRealPath());
            }
            if ($count++ > $maxTries) {
                throw new \RuntimeException('Can not lock the file: ' . $this->getRealPath());
            }
            usleep($triesTimeout);
        }
        return TRUE;
    }

    public function unlock()
    {
        return $this->flock(LOCK_UN);
    }

    public function getStringsCount()
    {
        if ($this->getFileSize() === 0) {
            return 0;
        }
        $flags = $this->clearFlags();
        $this->seek(PHP_INT_MAX);
        $key = $this->key();
        $this->fseekWithCheck(-1, SEEK_END);
        $lastChar = $this->fread(1);
        $shift = $lastChar === "\n" ? 0 : 1;
        $stringsCount = $key + $shift;
        $this->restoreFlags($flags);
        return $stringsCount;
    }

    public function deleteString($linePos)
    {
        $stringsCount = $this->getStringsCount();
        $maxLinePos = $stringsCount - 1;
        if ($linePos > $maxLinePos) {
            throw new \InvalidArgumentException(
            "Can not delete  \$linePos = $linePos . Max linePos is $maxLinePos"
            . " in file: \n" . $this->getRealPath()
            );
        }
        $flags = $this->clearFlags();
        if ($linePos === 0) {
            $this->rewind();
            $newCharPos = 0;
        } else {
            $this->seek($linePos - 1);
            $this->current();
            $newCharPos = $this->ftell();
            $this->next();
        }
        $deletedString = $this->current();
        $isItLastString = rtrim($deletedString, "\n") === $deletedString;
        $charPosFrom = $this->ftell();
        $this->moveBackward($charPosFrom, $newCharPos);
        $this->restoreFlags($flags);
    }

    public function fseekWithCheck($offset, $whence = SEEK_SET)
    {
        if ($this->fseek($offset, $whence) == -1) {
            throw new \RuntimeException("Can not fseek to $offset =  . $offset"
            . "\n in file: \n" . $this->getRealPath());
        }
        return 0;
    }

    public function fwriteWithCheck($string, $length = null)
    {
        $lengthForWrite = is_null($length) ? strlen($string) : $length;
        if ($lengthForWrite > strlen($string)) {
            throw new \InvalidArgumentException(
            '$length = ' . $length . " bigger then = strlen('$string') "
            . " in file: \n" . $this->getRealPath()
            );
        }
        $writedLength = $this->fwrite($string, $lengthForWrite);
        if ($writedLength !== $lengthForWrite) {
            throw new \RuntimeException('Error writing $string = ' . $string . " in file: \n" . $this->getRealPath());
        }
        return $writedLength;
    }

    public function getFileSize()
    {
        $position = $this->ftell();
        $this->fseekWithCheck(0, SEEK_END);
        $fileSize = $this->ftell();
        $this->fseekWithCheck($position);
        return $fileSize;
    }

    /**
     * If $beforeLinePos = null string append to the end
     *
     * @param string $insertedString string for insert. \n will be added if not exist.
     * @param type $beforeLinePos zero based line number. null for uppend to the end of file.
     */
    public function insertString($insertedString, $beforeLinePos = null)
    {

        $insertedString = rtrim($insertedString, "\r\n") . "\n";

        $stringsCount = $this->getStringsCount();
        if ($stringsCount === 0 && ( is_null($beforeLinePos) || $beforeLinePos === 0)) {

            $this->fseekWithCheck(0, SEEK_END);
            $this->fwriteWithCheck($insertedString);
            return;
        }

        if (is_null($beforeLinePos)) {
            $this->fseekWithCheck(-1, SEEK_END);
            $lastChar = $this->fread(1);
            $prefix = $lastChar === "\n" ? '' : "\n";
            $this->fwriteWithCheck($prefix . $insertedString);
            return;
        }

        $flags = $this->clearFlags();
        if ($beforeLinePos === 0) {
            $charPosFrom = 0;
            $this->seek($beforeLinePos);
        } else {
            $this->seek($beforeLinePos - 1);
            $this->current();
            $charPosFrom = $this->ftell();
        }
        if ($this->eof()) {
            throw new \InvalidArgumentException(
            '$beforeLinePos = ' . $beforeLinePos . " bigger then max index\n in file: \n" . $this->getRealPath()
            );
        }
        $newCharPos = $charPosFrom + strlen($insertedString);
        $this->moveForward($charPosFrom, $newCharPos);
        $this->fseekWithCheck($charPosFrom);
        $this->fwriteWithCheck($insertedString);
        $this->restoreFlags($flags);
    }

    public function rewriteString($newString, $inLinePos)
    {
        $stringsCount = $this->getStringsCount();
        $maxLinePos = $stringsCount - 1;
        if ($inLinePos > $maxLinePos) {
            throw new \InvalidArgumentException(
            "Can not rewrite  \$inLinePos = $inLinePos . Max linePos is $maxLinePos"
            . " in file: \n" . $this->getRealPath()
            );
        }
        $flags = $this->clearFlags();
        $newString = rtrim($newString, "\r\n") . "\n";
        if ($inLinePos === 0) {
            $charPosStart = 0;
            $this->rewind();
        } else {
            $this->seek($inLinePos - 1);
            $this->current();
            $charPosStart = $this->ftell();
            $this->next();
        }
        $this->current();

        $charPosFrom = $this->ftell();
        $charPosTo = $charPosStart + strlen($newString);
        $this->moveSubStr($charPosFrom, $charPosTo);
        $this->fseekWithCheck($charPosStart);
        $this->fwriteWithCheck($newString);
        $this->restoreFlags($flags);
    }

    public function truncateWithCheck($newFileSize, $placeholderChar = ' ')
    {
        $flags = $this->clearFlags();
        $chenges = $this->changeFileSize($newFileSize, $placeholderChar);
        $this->restoreFlags($flags);
        return $chenges;
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
        if ($charPosFrom === $newCharPos) {
            return;
        }
        $flags = $this->clearFlags();
        if ($charPosFrom < $newCharPos) {
            $this->moveForward($charPosFrom, $newCharPos);
        } else {
            $this->moveBackward($charPosFrom, $newCharPos);
        }
        $this->restoreFlags($flags);
    }

    protected function clearFlags()
    {
        $flagsForRestore = $this->getFlags();
        $this->setFlags($flagsForRestore & \SplFileObject::READ_CSV);
        return $flagsForRestore;
    }

    protected function restoreFlags($flagsForRestore)
    {
        $this->setFlags($flagsForRestore);
    }

}
