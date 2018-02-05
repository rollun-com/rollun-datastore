<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\test\datastore\Cleaner;

use rollun\datastore\DataStore\Memory as MemoryDataStore;
use rollun\datastore\Cleaner\CleanableListAdapter;
use rollun\datastore\Cleaner\Cleaner as DatastoreCleaner;
use rollun\utils\Cleaner\CleaningValidator\CallableValidator;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2013-08-25 at 15:44:45.
 */
class DatastoreCleanerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var DatastoreCleaner
     */
    protected $object;

    /**
     *
     * @var array
     */
    protected $dataStoreRecords = array(
        array('id' => 1, 'anotherId' => 10, 'fString' => 'val1', 'fFloat' => 400.0004),
        array('id' => 2, 'anotherId' => 20, 'fString' => 'error', 'fFloat' => 300.003),
        array('id' => 3, 'anotherId' => 40, 'fString' => 'error', 'fFloat' => 300.003),
        array('id' => 4, 'anotherId' => 30, 'fString' => 'val4', 'fFloat' => 100.1)
    );

    /**
     *
     * @var MemoryDataStore
     */
    protected $memoryDataStore;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->memoryDataStore = new MemoryDataStore();
        foreach ($this->dataStoreRecords as $val) {
            $this->memoryDataStore->create($val);
        }

        $callable = function ($dataStoreRecord) {
            return $dataStoreRecord['fString'] != 'error';
        };
        //make CallableValidator from function
        $callableValidator = new CallableValidator($callable);
        $this->object = new DatastoreCleaner($this->memoryDataStore, $callableValidator);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    public function test_Clean()
    {
        $this->object->cleaneList();
        $this->assertTrue($this->memoryDataStore->has(1));
        $this->assertFalse($this->memoryDataStore->has(2));
        $this->assertFalse($this->memoryDataStore->has(3));
        $this->assertTrue($this->memoryDataStore->has(4));
    }

}