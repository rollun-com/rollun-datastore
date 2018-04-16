<?php

namespace rollun\uploader\Iterator;

use rollun\datastore\DataStore\Interfaces\DataStoresInterface;
use rollun\dic\InsideConstruct;
use rollun\uploader\SeekableIterator;
use Traversable;
use Xiag\Rql\Parser\Node\LimitNode;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\GeNode;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\GtNode;
use Xiag\Rql\Parser\Node\SelectNode;
use Xiag\Rql\Parser\Node\SortNode;
use Xiag\Rql\Parser\Query;

class DataStorePack implements SeekableIterator
{
    /**
     * @var int
     */
    protected $limit;

    /**
     * @var DataStoresInterface
     */
    protected $dataStore;

    /**
     * @var array
     */
    protected $current = null;

    /**
     * DataStoreIterator constructor.
     * @param DataStoresInterface $dataStore
     * @param int $limit
     */
    public function __construct(DataStoresInterface $dataStore, $limit = 100)
    {
        $this->dataStore = $dataStore;
        $this->limit = $limit;
        $initItem = $this->dataStore->query($this->getInitQuery());
        if(!empty($initItem)) {
            $this->current = current($initItem);
        }
    }

    /**
     *
     */
    protected function getInitQuery() {
        $query = new Query();
        $query->setLimit(new LimitNode(1));
        $query->setSort(new SortNode([
            $this->dataStore->getIdentifier() => SortNode::SORT_ASC
        ]));
        return $query;
    }

    /**
     * Return query with limit and offset
     */
    protected function getQuery() {
        $query = new Query();
        if($this->valid()) {
            $query->setQuery(new GtNode($this->dataStore->getIdentifier(), $this->key()));
        }
        $query->setLimit(new LimitNode($this->limit));
        $query->setSort(new SortNode([
            $this->dataStore->getIdentifier() => SortNode::SORT_ASC
        ]));
        return $query;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        $data = $this->dataStore->query($this->getQuery());
        foreach ($data as $datum) {
            $this->current = $datum;
            yield;
        }
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->current[$this->dataStore->getIdentifier()];
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return (
            !is_null($this->current) &&
            $this->dataStore->has($this->current[$this->dataStore->getIdentifier()])
        );
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->current = null;
    }

    /**
     * Seeks to a position
     * @link TODO: add link to doc
     * @param mixed $position <p>
     * The position to seek to.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function seek($position)
    {
        $item = $this->dataStore->read($position);
        if (!isset($item) || empty($item)) {
            throw new \InvalidArgumentException("Position not valid or not found.");
        }
        $this->current = $item;
    }
}