<?php


namespace rollun\datastore\DataStore\Aspect;

use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\Traits\NoSupportCreateTrait;
use rollun\datastore\DataStore\Traits\NoSupportDeleteAllTrait;
use rollun\datastore\DataStore\Traits\NoSupportDeleteTrait;
use rollun\datastore\DataStore\Traits\NoSupportUpdateTrait;
use Xiag\Rql\Parser\Node\AbstractQueryNode;
use Xiag\Rql\Parser\Node\Query\AbstractComparisonOperatorNode;
use Xiag\Rql\Parser\Node\Query\AbstractLogicOperatorNode;
use Xiag\Rql\Parser\Node\SelectNode;
use Xiag\Rql\Parser\Node\SortNode;
use Xiag\Rql\Parser\Query;

abstract class AbstractMapperAspect extends AspectAbstract
{
    use NoSupportUpdateTrait;
    use NoSupportCreateTrait;
    use NoSupportDeleteAllTrait;
    use NoSupportDeleteTrait;

    /**
     * interface_field => datastore_field
     * @return string[]
     */
    abstract protected function getFieldsMap();

    /**
     * TODO: Remove to another aspect
     * interface_field => default_value
     * @return string[]
     */
    abstract protected function getNotSupportFields();

    /**
     * @param Query $query
     * @return Query
     * @throws DataStoreException
     */
    protected function preQuery(Query $query)
    {
        if (!is_null($query->getSelect())) {
            $query->setSelect($this->selectRepack($query->getSelect()));
        }
        if (!is_null($query->getSort())) {
            $query->setSort($this->sortRepack($query->getSort()));
        }
        if (!is_null($query->getQuery())) {
            $query->setQuery($this->queryNodeRepack($query->getQuery()));
        }
        return $query;
    }

    /**
     * @param AbstractQueryNode $node
     * @return AbstractQueryNode
     * @throws DataStoreException
     */
    protected function queryNodeRepack(AbstractQueryNode $node = null)
    {
        if (isset($node)) {
            if ($node instanceof AbstractLogicOperatorNode) {
                $repackNode = [];
                foreach ($node->getQueries() as $queryNode) {
                    $repackChildNode = $this->queryNodeRepack($queryNode);
                    if (isset($repackChildNode)) {
                        $repackNode[] = $repackChildNode;
                    }
                }
                $nodeClass = get_class($node);
                return new $nodeClass($repackNode);
            } else {
                /** @var AbstractComparisonOperatorNode $node */
                if (isset($this->getNotSupportFields()[$node->getField()])) {
                    return null;
                } else if (!isset($this->getFieldsMap()[$node->getField()])) {
                    throw new DataStoreException("Filed with name {$node->getField()} not found.");
                }
                $node->setField($this->getFieldsMap()[$node->getField()]);
                return $node;
            }
        }
        return null;
    }

    /**
     * @param SortNode $node
     * @return SortNode
     */
    protected function sortRepack(SortNode $node)
    {
        if (isset($node)) {
            $repackSort = new SortNode();
            foreach ($node->getFields() as $field => $direction) {
                $repackSort->addField($this->getFieldsMap()[$field], $direction);
            }
            return $repackSort;
        }
        return null;
    }

    /**
     * @param SelectNode $node
     * @return SelectNode
     */
    protected function selectRepack(SelectNode $node)
    {
        if (isset($node)) {
            $fields = [];
            foreach ($node->getFields() as $field) {
                $fields[] = $this->getFieldsMap()[$field];
            }
            return new SelectNode($fields);
        }
        return null;
    }

    /**
     * @param Query $query
     * @return array|array[]|mixed
     * @throws DataStoreException
     */
    public function query(Query $query)
    {
        $newQuery = $this->preQuery((clone $query));
        $result = $this->dataStore->query($newQuery);
        return $this->postQuery($result, $newQuery);
    }


    /**
     * @param mixed $result
     * @param Query $query
     * @return array
     */
    protected function postQuery($result, Query $query)
    {
        $repackResult = [];
        foreach ($result as $item) {
            $repackResult[] = $this->postRead($item, $item[$this->dataStore->getIdentifier()]);
        }
        return $repackResult;
    }

    /**
     * @param mixed $item
     * @param $id
     * @return array
     */
    protected function postRead($item, $id)
    {
        if(is_null($item)) return null;
        $repackItem = [];
        foreach ($this->getFieldsMap() as $interfaceName => $priceName) {
            $repackItem[$interfaceName] = isset($item[$priceName]) ? trim($item[$priceName]) : null;
        }
        foreach ($this->getNotSupportFields() as $filed => $value) {
            $repackItem[$filed] = $value;
        }
        return $repackItem;
    }
}