<?php


namespace rollun\uploader;

use IteratorAggregate;
use rollun\callback\Callback\CallbackInterface;
use rollun\datastore\DataStore\Interfaces\DataStoresInterface;
use rollun\dic\InsideConstruct;
use rollun\uploader\SeekableIterator;

class Uploader
{
    /**
     * @var  IteratorAggregate
     */
    protected $sourceDataIteratorAggregator;

    /**
     * @var DataStoresInterface
     */
    protected $destinationDataStore;

    /**
     * @var mixed Iterator position
     */
    protected $key = null;

    /**
     * Uploader constructor.
     * @param IteratorAggregate $sourceDataIteratorAggregator
     * @param DataStoresInterface $destinationDataStore
     */
    public function __construct(
        IteratorAggregate $sourceDataIteratorAggregator,
        DataStoresInterface $destinationDataStore
    ) {
        $this->sourceDataIteratorAggregator = $sourceDataIteratorAggregator;
        $this->destinationDataStore = $destinationDataStore;
    }

    public function upload()
    {
        $iterator = $this->sourceDataIteratorAggregator->getIterator();
        if(isset($this->key) && $iterator instanceof SeekableIterator) {
            $iterator->seek($this->key); //set iterator to last position.
        }
        foreach ($iterator as $key => $value) {
            $this->key = $key;
            $this->destinationDataStore->create($value, true);
        }
    }

    /**
     * @param $v
     */
    public function __invoke($v = null)
    {
        $this->upload();
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return [
            "iteratorAggregate",
            "destinationDataStore",
            "key",
        ];
    }

    /**
     *
     */
    public function __wakeup()
    {
        $this->__construct($this->sourceDataIteratorAggregator, $this->destinationDataStore);
    }
}