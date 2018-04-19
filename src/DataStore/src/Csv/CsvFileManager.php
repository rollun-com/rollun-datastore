<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\Csv;

use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\RestException;
use Zend\Db\Adapter;
use Zend\Db\Metadata\Source;
use Zend\Db\Metadata\Source\Factory;
use Zend\Db\Sql;
use Zend\Db\Sql\Ddl\AlterTable;
use Zend\Db\Sql\Ddl\Constraint;
use Zend\Db\Sql\Ddl\Constraint\UniqueKey;
use Zend\Db\Sql\Ddl\CreateTable;

/**
 * Creates table and gets its info
 *
 * Uses:
 * <code>
 *  $csvFileManager = new CsvFileManager($dataDir = 'data' );
 *  $tableData = [
 *      'id' => [
 *          ]
 *      ],
 *      'name' => [
 *          ]
 *      ]
 *  ];
 *  $csvFileManager->createCsvFile($fileName, $dirMame = 'data/csv');
 * </code>
 *
 * As you can see, array $tableData has 4 keys and next structure:
 * <code>
 *  $tableData = [
 *      'FieldName' => [
 *          'field_type' => 'Integer',
 *          'field_params' => [
 *              'options' => ['autoincrement' => true]
 *          ],
 *          'field_foreign_key' => [
 *              'referenceTable' => ... ,
 *              'referenceColumn' => ... ,
 *              'onDeleteRule' => null, // ' 'cascade'
 *              'onUpdateRule' => null, //
 *              'name' => null  // or Constraint Name
 *          ],
 *          'field_unique_key' => true // or Constraint Name
 *      ],
 *
 *  ...
 * </code>
 *
 * About value of key <b>'field_type'</b> - see {@link TableManagerMysql::$fieldClasses}<br>
 * About value of key <b>'field_params'</b> - see {@link TableManagerMysql::$parameters}<br>
 *
 * The <b>'options'</b> may be:
 * <ul>
 * <li>unsigned</li>
 * <li>zerofill</li>
 * <li>identity</li>
 * <li>serial</li>
 * <li>autoincrement</li>
 * <li>comment</li>
 * <li>columnformat</li>
 * <li>format</li>
 * <li>storage</li>
 * </ul>
 *
 * select * from INFORMATION_SCHEMA.COLUMNS where column_name like 'TABLE%'
 * SELECT RC.TABLE_NAME, RC.REFERENCED_TABLE_NAME, KCU.COLUMN_NAME, KCU.REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS RC JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU USING(CONSTRAINT_NAME)
 *
 *
 * @see Examples/TableGateway/index.php
 * @category   rest
 * @package    zaboy
 */
class CsvFileManager
{

    const LOCK_TRIES_TIMEOUT = 50; //in ms
    const MAX_LOCK_TRIES = 40;

    public function __construct()
    {

    }

    public function getFullFilename($filename, $dirname = null)
    {
        $filename = trim($filename, ' ');
        if (substr($filename, 0, 1) == '\\' or substr($filename, 0, 1) == '/') {
            throw new \RuntimeException('Filename must be a relative path: ' . $filename);
        }
        $dirname = empty($dirname) ? '..' : $dirname;
        $dirname = rtrim($dirname, '/\\ ');
        $fullFilename = $dirname . DIRECTORY_SEPARATOR . $filename;
        return $fullFilename;
    }

    public function createDir($dirname)
    {
        if (!(file_exists($dirname) && is_dir($dirname))) {
            try {
                $result = mkdir($dirname, 0777, true);
            } catch (\Exception $exc) {
                throw new \RuntimeException(
                $exc->getMessage() . PHP_EOL
                . 'Dir name: ' . $dirname
                );
            }
            if (!$result) {
                throw new \RuntimeException('Wrong dir name: ' . $dirname);
            }
        }
    }

    /**
     * You have to unlock and close $stream after using
     *
     * @param type $fullFilename
     * @param type $rewriteIfExist
     * @return stream
     * @throws DataStoreException
     */
    public function createAndOpenFile($fullFilename, $rewriteIfExist = false)
    {
        $dirname = dirname($fullFilename);
        $this->createDir($dirname);

        if (file_exists($fullFilename) && is_file($fullFilename)) {
            if ($rewriteIfExist) {
                $stream = fopen('c+', $fullFilename);
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
            $stream = fopen('c+');
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

    /**
     *
     * IF $dirname is file - it will be delete
     *
     * @param type $dirname
     * @return boolean
     * @throws \RuntimeException
     */
    public function deleteRecursively($dirname)
    {

        if (!realpath($dirname)) {
            throw new \RuntimeException('Wrong dir name: ' . $dirname);
        }
        if (!file_exists($dirname)) {
            return true;
        }

        if (!is_dir($dirname)) {
            try {
                return unlink($dirname);
            } catch (\Exception $exc) {
                throw new \RuntimeException($exc->getMessage() . ' Filename: ' . $dirname);
            }
        }

        foreach (scandir($dirname) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteRecursively($dirname . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dirname);
    }

}
