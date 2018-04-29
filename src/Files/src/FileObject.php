<?php

namespace rollun\files;

use Ajgl\Csv\Rfc;

class FileObject extends Rfc\Spl\SplFileObject
{

    const DELAULT_LOCK_TRIES_TIMEOUT = 50; //in ms
    const DELAULT_MAX_LOCK_TRIES = 40;

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
        $this->setFlags(\SplFileObject::READ_AHEAD); //| \SplFileObject::DROP_NEW_LINE | \SplFileObject::READ_AHEAD |\SplFileObject: \SplFileObject::SKIP_EMPTY
        $this->setMaxBufferSize(static::DELAULT_MAX_BUFFER_SIZE);
        $this->lockTriesTimeout = static::DELAULT_LOCK_TRIES_TIMEOUT;
        $this->maxLockTries = static::DELAULT_MAX_LOCK_TRIES;
        $this->currentLockMode = 0;
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

    public function deleteRow($linePos)
    {
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
        $this->current();
        $charPosFrom = $this->ftell();
        $this->moveBackward($charPosFrom, $newCharPos);
        $this->restoreFlags($flags);
    }

    public function fseekWithCheck($offset, $whence = SEEK_SET)
    {
        if ($this->fseek($offset, $whence) == -1) {
            throw new \RuntimeException('$charPosForRead =' . $charPosForRead . " in file: \n" . $this->getRealPath());
        }
        return 0;
    }

    public function fwriteWithCheck($string, $length = null)
    {
        $lengthForWrite = is_null($length) ? strlen($string) : $length;
        if ($lengthForWrite > strlen($string)) {
            throw new \InvalidArgumentException('$length = ' . $length . " bigger then   = strlen('$string')");
        }
        $writedLength = $this->fwrite($string, $lengthForWrite);
        if ($writedLength !== $lengthForWrite) {
            throw new \RuntimeException('Error writing $string = ' . $string . " in file: \n" . $this->getRealPath());
        }
        return $writedLength;
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
        if (is_null($beforeLinePos)) {
            $this->fseekWithCheck(0, SEEK_END);
            $this->fwriteWithCheck($insertedString);
            return;
        }

        $flags = $this->clearFlags();
        if ($beforeLinePos === 0) {
            $charPosFrom = 0;
        } else {
            $this->seek($beforeLinePos - 1);
            $this->current();
            $charPosFrom = $this->ftell();
        }
        $newCharPos = $charPosFrom + strlen($insertedString);
        $this->moveForward($charPosFrom, $newCharPos);
        $this->fseekWithCheck($charPosFrom);
        $this->fwriteWithCheck($insertedString);
        $this->restoreFlags($flags);
    }

    public function makeFileLonger($newFileSize, $placeholderChar = ' ')
    {
        $this->fseekWithCheck(0, SEEK_END);
        $fileSize = $this->ftell();
        if ($fileSize >= $newFileSize) {
            return false;
        }
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

    protected function moveForward($charPosFrom, $newCharPos)
    {
        $this->fseekWithCheck(0, SEEK_END);
        $fileSize = $this->ftell();
        $changes = $this->changeFileSize($fileSize + $newCharPos - $charPosFrom);
        $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $fileSize ? $fileSize - $charPosFrom : $this->getMaxBufferSize();
        $charPosForRead = $fileSize - $bufferSize;
        $charPosForWrite = $fileSize + $newCharPos - $charPosFrom - $bufferSize;
        while ($bufferSize > 0) {
            $this->fseekWithCheck($charPosForRead);
            $buffer = $this->fread($bufferSize);
            $this->fseekWithCheck($charPosForWrite);
            $this->fwriteWithCheck($buffer);
            $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $charPosForRead ? $charPosForRead - $charPosFrom : $this->getMaxBufferSize();
            $charPosForRead = $charPosForRead - $bufferSize;
            $charPosForWrite = $charPosForWrite - $bufferSize;
        }
        $this->fflush();
    }

    protected function moveBackward($charPosFrom, $newCharPos)
    {
        $this->fseekWithCheck(0, SEEK_END);
        $fileSize = $this->ftell();
        $this->fseekWithCheck($charPosFrom);
        while ($charPosFrom < $fileSize) {
            $this->fseekWithCheck($charPosFrom);
            $bufferSize = ($charPosFrom + $this->getMaxBufferSize()) > $fileSize ? $fileSize - $charPosFrom : $this->getMaxBufferSize();
            $buffer = $this->fread($bufferSize);
            $charPosFrom = $this->ftell();
            $this->fseekWithCheck($newCharPos);
            $this->fwriteWithCheck($buffer);
            $newCharPos = $this->ftell();
        }
        $this->fflush();
        $this->changeFileSize($newCharPos);
    }

    /**
     *
     * @param int $newFileSize
     * @param string $placeholderChar if $newFileSize > $this->fileeSithe()
     * @param int $oldFileSize - do not set this fild!
     * @return int
     * @throws \RuntimeException
     */
    protected function changeFileSize($newFileSize, $placeholderChar = ' ', $oldFileSize = null)
    {
        $this->fseekWithCheck(0, SEEK_END);
        $fileSize = $this->ftell();
        if ($newFileSize === $fileSize) {
            return 0;
        }

        if ($newFileSize < $fileSize) {
            $this->ftruncate($newFileSize);
            return $newFileSize - $fileSize;
        }

        $oldFileSize = $oldFileSize ?? $fileSize;
        $addQuantity = $this->getMaxBufferSize() < ($newFileSize - $fileSize) ?
                $this->getMaxBufferSize() :
                $newFileSize - $fileSize;
        $string = str_repeat($placeholderChar, $addQuantity);
        $this->fwriteWithCheck($string);
        $this->fseekWithCheck(0, SEEK_END);
        $currentFileSize = $this->ftell();
        if ($currentFileSize == $fileSize) {
            throw new \RuntimeException("Error changeFileSize to $newFileSize bytes  \n in file: \n" . $this->getRealPath());
        }
        if ($currentFileSize == $newFileSize) {
            return $newFileSize - $oldFileSize;
        } else {
            $this->changeFileSize($newFileSize, $placeholderChar);
        }
    }

}
