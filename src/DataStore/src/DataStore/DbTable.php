<?php

/**
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license LICENSE.md New BSD License
 */

namespace rollun\datastore\DataStore;

use InvalidArgumentException;
use rollun\datastore\DataStore\ConditionBuilder\SqlConditionBuilder;
use rollun\datastore\Rql\RqlQuery;
use rollun\datastore\TableGateway\DbSql\MultiInsertSql;
use rollun\datastore\TableGateway\SqlQueryBuilder;
use Xiag\Rql\Parser\Node\Query\ArrayOperator\InNode;
use Xiag\Rql\Parser\Query;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;

/**
 * Datastore as db table
 *
 * Class DbTable
 * @package rollun\datastore\DataStore
 */
class DbTable extends DataStoreAbstract
{
    /**
     * @var TableGateway
     */
    protected $dbTable;

    /**
     * @var SqlQueryBuilder
     */
    protected $sqlQueryBuilder;

    /**
     * DbTable constructor.
     * @param TableGateway $dbTable
     */
    public function __construct(TableGateway $dbTable)
    {
        $this->dbTable = $dbTable;
    }

    protected function getSqlQueryBuilder()
    {
        if ($this->sqlQueryBuilder == null) {
            $this->sqlQueryBuilder = new SqlQueryBuilder($this->dbTable->getAdapter(), $this->dbTable->table);
        }

        return $this->sqlQueryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function create($itemData, $rewriteIfExist = false)
    {
        if ($rewriteIfExist) {
            // 'rewriteIfExist' do not work with multiply insert
            trigger_error("Option 'rewriteIfExist' is no more use", E_USER_DEPRECATED);
        }

        $adapter = $this->dbTable->getAdapter();
        $adapter->getDriver()->getConnection()->beginTransaction();

        try {
            $insertedItem = !$rewriteIfExist ?
                $this->insertItem($itemData) : $this->upserItem($itemData);

            $adapter->getDriver()->getConnection()->commit();
        } catch (\Throwable $e) {
            $adapter->getDriver()->getConnection()->rollback();
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't insert item. {$e->getMessage()}", 0, $e);
        }

        return $insertedItem;
    }


    /**
     * @param $item
     */
    protected function upserItem($item)
    {
        $id = $item[$this->getIdentifier()];

        $adapter = $this->dbTable->getAdapter();

        $valTemplate = '';
        foreach (array_values($item) as $_) {
            $valTemplate .= '?,';
        }
        $valTemplate = trim($valTemplate, ",");

        $upsertTemplate = '';
        foreach ($item as $key => $_) {
            $upsertTemplate .= "{$adapter->getPlatform()->quoteIdentifier($key)} = ?,";
        }
        $upsertTemplate = trim($upsertTemplate, ",");

        $vals = array_merge(array_values($item), array_values($item));

        $keys = implode(',', array_map([$adapter->getPlatform(), 'quoteIdentifier'], array_keys($item)));

        $sqlString = "INSERT INTO " .
            $adapter->getPlatform()->quoteIdentifier($this->dbTable->getTable())
            . "(" . $keys . ") "
            . "VALUES ({$valTemplate})"
            . "ON DUPLICATE KEY UPDATE $upsertTemplate";

        $statement = $adapter->getDriver()->createStatement($sqlString);
        $statement->setParameterContainer(new ParameterContainer($vals));
        $statement->execute();

        return $this->read($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $item
     * @return array|mixed|null
     * @throws DataStoreException
     */
    public function rewrite($item)
    {
        if (!isset($item[$this->getIdentifier()])) {
            throw new DataStoreException('Item must has primary key');
        }
        $adapter = $this->dbTable->getAdapter();
        $adapter->getDriver()->getConnection()->beginTransaction();

        try {
            $result = $this->updateItem($item);

            $adapter->getDriver()->getConnection()->commit();
        } catch (\Throwable $e) {
            $adapter->getDriver()->getConnection()->rollback();
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't upsert item. {$e->getMessage()}", 0, $e);
        }

        return $result;
    }


    /**
     * @param $itemData
     * @param bool $rewriteIfExist
     * @return array|mixed|null
     */
    protected function insertItem($itemData, $rewriteIfExist = false)
    {
        if ($rewriteIfExist && isset($itemData[$this->getIdentifier()])) {
            $this->delete($itemData[$this->getIdentifier()]);
        }

        $this->dbTable->insert($itemData);

        if (isset($itemData[$this->getIdentifier()])) {
            $insertedItem = $this->read($itemData[$this->getIdentifier()]);
        } else {
            trigger_error("Autoincrement 'id' is not allowed", E_USER_DEPRECATED);
            $id = $this->dbTable->getLastInsertValue();
            $insertedItem = $this->read($id);
        }

        return $insertedItem;
    }

    /**
     * {@inheritdoc}
     */
    public function update($itemData, $createIfAbsent = false)
    {
        if ($createIfAbsent) {
            trigger_error("Option 'createIfAbsent' is no more use.", E_USER_DEPRECATED);
        }

        if (!isset($itemData[$this->getIdentifier()])) {
            throw new DataStoreException('Item must has primary key');
        }

        $adapter = $this->dbTable->getAdapter();
        $adapter->getDriver()->getConnection()->beginTransaction();

        try {
            $result = $this->updateItem($itemData, $createIfAbsent);
            $adapter->getDriver()->getConnection()->commit();
        } catch (\Throwable $e) {
            $adapter->getDriver()->getConnection()->rollback();
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't update item. {$e->getMessage()}", 0, $e);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param Query $query
     * @return array
     * @throws DataStoreException
     */
    public function queriedDelete(Query $query)
    {
        if (
            $query->getLimit()
            || $query->getSort()
            || ($query instanceof RqlQuery && $query->getGroupBy())
            || $query->getSelect()
        ) {
            throw new InvalidArgumentException('Only where clause allowed for delete');
        }

        $conditionBuilder = new SqlConditionBuilder(
            $this->getDbTable()->getAdapter(),
            $this->getDbTable()->getTable()
        );

        $sql = new Sql($this->getDbTable()->getAdapter());
        $delete = $sql->delete($this->getDbTable()->getTable());
        $delete->where($conditionBuilder->__invoke($query->getQuery()));
        $sqlString = $sql->buildSqlString($delete);

        $adapter = $this->dbTable->getAdapter();
        $shouldDeletedIds = array_map(function ($item) {
            return $item[$this->getIdentifier()];
        }, $this->query($query));

        try {
            $statement = $adapter->getDriver()->createStatement($sqlString);
            $result = $statement->execute();
        } catch (\Throwable $e) {
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't delete records using query", 0, $e);
        }

        if (count($shouldDeletedIds) === $result->getAffectedRows()) {
            return $shouldDeletedIds;
        }

        $result = $this->query($query);

        foreach ($result as $record) {
            if (in_array($record[$this->getIdentifier()], $shouldDeletedIds)) {
                unset($shouldDeletedIds[$record[$this->getIdentifier()]]);
            }
        }

        return $shouldDeletedIds;
    }

    /**
     * {@inheritdoc}
     *
     * @param $record
     * @param Query $query
     * @return array
     */
    public function queriedUpdate($record, Query $query)
    {
        if (
            $query->getLimit()
            || $query->getSort()
            || ($query instanceof RqlQuery && $query->getGroupBy())
            || $query->getSelect()
        ) {
            throw new InvalidArgumentException('Only where clause allowed for update');
        }

        $selectResult = $this->selectForUpdateWithQuery($query);

        if (!$selectResult) {
            return [];
        }

        $conditionBuilder = new SqlConditionBuilder(
            $this->getDbTable()->getAdapter(),
            $this->getDbTable()->getTable()
        );

        // prepare record
        foreach ($record as $k => $v) {
            if ($v === false) {
                $record[$k] = 0;
            } elseif ($v === true) {
                $record[$k] = 1;
            }
        }

        $sql = new Sql($this->getDbTable()->getAdapter());
        $update = $sql->update($this->getDbTable()->getTable());
        $update->where($conditionBuilder->__invoke($query->getQuery()));
        $update->set($record);
        $sqlString = $sql->buildSqlString($update);

        $adapter = $this->dbTable->getAdapter();

        try {
            $statement = $adapter->getDriver()->createStatement($sqlString);
            $updateResult = $statement->execute();
            $updatedIds = [];

            if ($selectResult->getAffectedRows() === $updateResult->getAffectedRows()) {
                /** @noinspection SuspiciousLoopInspection */
                foreach ($selectResult as $record) {
                    $updatedIds[] = $record[$this->getIdentifier()];
                }
            } else {
                $effectedRecords = $this->query($query);
                /** @noinspection SuspiciousLoopInspection */
                foreach ($selectResult as $record) {
                    if ($record !== $effectedRecords[$this->getIdentifier()]) {
                        $updatedIds[] = $effectedRecords[$this->getIdentifier()];
                    }
                }
            }
        } catch (\Throwable $e) {
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't update records using query", 0, $e);
        }

        return $updatedIds;
    }

    /**
     * @param array $identifiers
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    private function selectForUpdateWithIds(array $identifiers)
    {
        $adapter = $this->dbTable->getAdapter();
        $valTemplate = '';

        foreach ($identifiers as $identifier) {
            $valTemplate .= '?,';
        }

        $valTemplate = trim($valTemplate, ",");
        $sqlString = "SELECT " . Select::SQL_STAR
            . " FROM {$adapter->getPlatform()->quoteIdentifier($this->dbTable->getTable())}"
            . " WHERE {$adapter->getPlatform()->quoteIdentifier($this->getIdentifier())} IN ($valTemplate)"
            . " FOR UPDATE";

        $statement = $adapter->getDriver()->createStatement($sqlString);
        $statement->setParameterContainer(new ParameterContainer($identifiers));

        return $statement->execute();
    }

    /**
     * @param Query $query
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    private function selectForUpdateWithQuery(Query $query)
    {
        $adapter = $this->dbTable->getAdapter();

        $sqlString = $this->getSqlQueryBuilder()->buildSql($query);
        $sqlString .= " FOR UPDATE";

        $statement = $adapter->getDriver()->createStatement($sqlString);

        return $statement->execute();
    }

    /**
     * @param $itemData
     * @param bool $createIfAbsent
     * @return array|mixed|null
     * @throws DataStoreException
     */
    protected function updateItem($itemData, $createIfAbsent = false)
    {
        $identifier = $this->getIdentifier();
        $id = $itemData[$identifier];
        $this->checkIdentifierType($id);

        // Is row with this index exist ?
        $result = $this->selectForUpdateWithIds([$id]);
        $isExist = $result->count();

        if (!$isExist && $createIfAbsent) {
            $this->dbTable->insert($itemData);
        } elseif ($isExist) {
            unset($itemData[$identifier]);
            $this->dbTable->update($itemData, [$identifier => $id]);
        } else {
            throw new DataStoreException("[{$this->dbTable->getTable()}]Can't update item with id = $id");
        }

        return $this->read($id);
    }

    /**
     * {@inheritdoc}
     */
    public function query(Query $query)
    {
        $adapter = $this->dbTable->getAdapter();
        $sqlString = $this->getSqlQueryBuilder()->buildSql($query);

        try {
            $statement = $adapter->getDriver()->createStatement($sqlString);
            $resultSet = $statement->execute();
        } catch (\PDOException $exception) {
            throw new DataStoreException(
                "Error by execute '$sqlString' query to {$this->getDbTable()->getTable()}.",
                500,
                $exception
            );
        }

        $result = [];

        foreach ($resultSet as $itemData) {
            $result[] = $itemData;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $identifier = $this->getIdentifier();
        $this->checkIdentifierType($id);
        $element = $this->read($id);

        if ($element) {
            $this->dbTable->delete([$identifier => $id]);
        }

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function read($id)
    {
        $this->checkIdentifierType($id);
        $identifier = $this->getIdentifier();
        $rowSet = $this->dbTable->select([$identifier => $id]);
        $row = $rowSet->current();

        if (isset($row)) {
            return $row->getArrayCopy();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $where = '1=1';
        return $this->dbTable->delete($where);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $adapter = $this->dbTable->getAdapter();

        $sql = 'SELECT COUNT(*) AS count FROM ' . $adapter->getPlatform()->quoteIdentifier($this->dbTable->getTable());

        $statement = $adapter->getDriver()->createStatement($sql);
        $result = $statement->execute();

        return $result->current()['count'];
    }

    /**
     * @param array $itemsData
     * @return array|array[]
     * @throws DataStoreException
     */
    public function multiCreate($itemsData)
    {
        $multiInsertTableGw = $this->createMultiInsertTableGw();
        $multiInsertTableGw->getAdapter()->getDriver()->getConnection()->beginTransaction();
        $getIdCallable = function ($item) {
            return $item[$this->getIdentifier()];
        };

        try {
            $identifiers = array_map($getIdCallable, $itemsData);
            $multiInsertTableGw->insert($itemsData);

            $query = new Query();
            $query->setQuery(new InNode($this->getIdentifier(), $identifiers));
            $insertedItems = $this->query($query);
            $multiInsertTableGw->getAdapter()->getDriver()->getConnection()->commit();
        } catch (\Throwable $throwable) {
            $multiInsertTableGw->getAdapter()->getDriver()->getConnection()->rollback();

            throw new DataStoreException(
                "Exception by multi create to table {$this->dbTable->table}. Details: {$throwable->getMessage()}",
                500,
                $throwable
            );
        }

        return array_map($getIdCallable, $insertedItems);
    }

    /**
     * @return TableGateway
     */
    public function getDbTable()
    {
        return $this->dbTable;
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeys()
    {
        $identifier = $this->getIdentifier();
        $select = $this->dbTable->getSql()->select();
        $select->columns([$identifier]);

        $resultSet = $this->dbTable->selectWith($select);
        $keys = [];

        foreach ($resultSet as $key => $result) {
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @return TableGateway
     */
    private function createMultiInsertTableGw()
    {
        $multiInsertTableGw = new TableGateway(
            $this->dbTable->getTable(),
            $this->dbTable->getAdapter(),
            $this->dbTable->getFeatureSet(),
            $this->dbTable->getResultSetPrototype(),
            new MultiInsertSql($this->dbTable->getAdapter(), $this->dbTable->getTable())
        );

        return $multiInsertTableGw;
    }
}
