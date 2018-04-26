<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\files;

class FileManager
{

    const LOCK_TRIES_TIMEOUT = 50; //in ms
    const MAX_LOCK_TRIES = 40;

    /**
     * joinPath(('C:/', '/dir', ' \file.csv') -> 'C:/dir/file.csv'
     * see tests
     *
     * @param string various number of params ' C:/', '\dir/', ' \file.csv',
     * @return string C:/dir/file.csv
     */
    public function joinPath()
    {
        $paths = [];
        $arguments = func_get_args();
        foreach ($arguments as $arg) {
            if (trim($arg, ' ') !== '') {
                $paths[] = trim($arg, ' ');
            }
        }
        return str_replace('p:/', 'p://', preg_replace('#/+#', '/', str_replace('\\', '/', join('/', $paths))));
    }

    public function createDir($dirname)
    {
        if (!(file_exists($dirname) && is_dir($dirname))) {
            try {
                $result = mkdir($dirname, 0777, true);
            } catch (\Exception $exc) {
                throw new \RuntimeException(
                $exc->getMessage() . PHP_EOL
                . ' Dir name: ' . $dirname
                );
            }
            if (!$result) {
                throw new \RuntimeException('Wrong dir name: ' . $dirname);
            }
        }
    }

    /**
     *
     * IF $dirname is file - it will be delete
     *
     * @param type $dirname
     * @return boolean
     * @throws \RuntimeException
     */
    public function deleteDirRecursively($dirname)
    {

        if (!realpath($dirname)) {
            throw new \RuntimeException('Wrong dir name: ' . $dirname);
        }
        if (!file_exists($dirname)) {
            return true;
        }
        if (!is_dir($dirname)) {
            try {
                return $this->deleteFile($dirname);
            } catch (\Exception $exc) {
                throw new \RuntimeException($exc->getMessage() . ' Filename: ' . $dirname);
            }
        }
        foreach (scandir($dirname) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirRecursively($dirname . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dirname);
    }

    public function openFile($fullFilename, $mode = 'r')
    {
        $count = 0;
        while (!$stream = fopen($fullFilename, $mode)) {
            if ($count++ > static::MAX_LOCK_TRIES) {
                throw new \RuntimeException('Can not open the file: ' . $fullFilename);
            }
            usleep(static::LOCK_TRIES_TIMEOUT);
        }
        return $stream;
    }

    /**
     * You have to unlock and close $stream after using
     * Use for create only:
     * $this->closeStream( $this->createAndOpenFile($fullFilename) );
     *
     * Use for open only:
     * $this->openFile($fullFilename,'w+');
     *
     * @param type $fullFilename
     * @param type $rewriteIfExist
     * @return stream
     */
    public function createAndOpenFile($fullFilename, $rewriteIfExist = false)
    {
        $dirname = dirname($fullFilename);
        $this->createDir($dirname);

        if (file_exists($fullFilename) && is_file($fullFilename)) {
            if ($rewriteIfExist) {
                $stream = $this->openFile($fullFilename, 'c+');
                $this->lockEx($stream);
                ftruncate($stream, 0);
                return $stream;
            } else {
                throw new \RuntimeException('File ' . $fullFilename . ' already exists');
            }
        } else {
            $stream = fopen($fullFilename, 'w+');
            $this->lockEx($stream, $fullFilename);
        }
        return $stream;
    }

    public function closeStream($stream)
    {
        flock($stream, LOCK_UN);
        fclose($stream);
    }

    public function deleteFile($fullFilename)
    {
        if (!realpath($fullFilename)) {
            throw new \RuntimeException('Wrong file name: ' . $fullFilename);
        }
        if (file_exists($fullFilename) && is_file($fullFilename)) {
            $stream = $this->openFile($fullFilename, 'c+');
            $this->lockEx($stream);
            $this->closeStream($stream);
            unlink($fullFilename);
        }
        return true;
    }

    protected function lockEx($stream, $fullFilename = '')
    {
        $count = 0;
        while (!flock($stream, LOCK_EX | LOCK_NB, $wouldblock)) {
            if (!$wouldblock) {
                throw new \RuntimeException('There is a problem with file: ' . $fullFilename);
            }
            if ($count++ > static::MAX_LOCK_TRIES) {
                throw new \RuntimeException('Can not lock the file: ' . $fullFilename);
            }
            usleep(static::LOCK_TRIES_TIMEOUT);
        }
    }

}
