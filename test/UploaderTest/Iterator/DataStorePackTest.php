<?php

namespace rollun\test\uploader\Uploader;

use rollun\datastore\DataStore\Interfaces\DataStoresInterface;
use rollun\datastore\DataStore\Memory;
use rollun\uploader\Iterator\DataStorePack as DataStorePackIterator;
use PHPUnit\Framework\TestCase;
use Xiag\Rql\Parser\Query;

class DataStorePackTest extends TestCase
{
    /** @var DataStorePackIterator */
    protected $object;

    /** @var DataStoresInterface */
    protected $dataStore;

    public function setUp()
    {
        $this->dataStore = new Memory();
        foreach (range(1, 10) as $num) {
            $this->dataStore->create([
                "id" => $num,
                "name" => "name$num"
            ]);
        }
        $this->object = new DataStorePackIterator($this->dataStore);
    }

    public function testCurrent()
    {
        $item = $this->object->current();
        $expected = $this->dataStore->read($this->object->key());
        $this->assertEquals($expected, $item);
    }

    /**
     */
    public function testSeek()
    {
        foreach ($this->dataStore->query(new Query()) as $expected) {
            $this->object->seek($expected["id"]);
            $item = $this->object->current();
            $this->assertEquals($expected, $item);
        }
    }
}
