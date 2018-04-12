<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore;

use rollun\datastore\DataStore\ConditionBuilder\ConditionBuilderAbstract;
use rollun\datastore\DataStore\Interfaces\DataStoresInterface;
use rollun\datastore\DataStore\Iterators\DataStoreIterator;
use rollun\datastore\Rql\Node\AggregateFunctionNode;
use rollun\datastore\Rql\RqlQuery;
use Xiag\Rql\Parser\Node;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\EqNode;
use Xiag\Rql\Parser\Node\SortNode;
use Xiag\Rql\Parser\Query;

/**
 * Abstract class for DataStores
 *
 * @todo make support null in eq(fieldname, null) and ne(fieldname, null)
 * @todo JsonSerializable https://github.com/zendframework/zend-diactoros/blob/master/doc/book/custom-responses.md#json-responses
 * @todo Adapter paras to config for tests
 * @todo Excel client
 * @todo CSV Store
 * @see http://en.wikipedia.org/wiki/Create,_read,_update_and_delete
 * @category   rest
 * @package    zaboy
 */
abstract class DataStoreAbstract implements DataStoresInterface
{

    /**
     *
     * @var ConditionBuilderAbstract
     */
    protected $conditionBuilder;

//** Interface "rollun\datastore\DataStore\Interfaces\ReadInterface" **/

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function has($id)
    {
        return !(empty($this->read($id)));
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function read($id)
    {
        $identifier = $this->getIdentifier();
        $this->checkIdentifierType($id);
        $query = new Query();
        $eqNode = new EqNode($identifier, $id);
        $query->setQuery($eqNode);
        $queryResult = $this->query($query);
        if (empty($queryResult)) {
            return null;
        } else {
            return $queryResult[0];
        }
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return static::DEF_ID;
    }

    /**
     * Throw Exception if type of Identifier is wrong
     *
     * @param mix $id
     */
    protected function checkIdentifierType($id)
    {
        $idType = gettype($id);
        if ($idType == 'integer' || $idType == 'double' || $idType == 'string') {
            return;
        } else {
            throw new DataStoreException("Type of Identifier is wrong - " . $idType);
        }
    }

// ** Interface "rollun\datastore\DataStore\Interfaces\DataStoresInterface"  **/

    private function validateQuery(Query $query) {
        $groupFields = ($query instanceof RqlQuery && $query->getGroupby() != null) ? $query->getGroupby()->getFields() : [];
        $selectionFields = $query->getSelect()->getFields();
        foreach ($selectionFields as &$field) {
            if (!in_array($field, $groupFields) && !($field instanceof AggregateFunctionNode)) {
                throw new DataStoreException("Query is not valid. Selected $field is not GroupBy or Aggregate field.");
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function query(Query $query)
    {
        $this->validateQuery($query);
        $limitNode = $query->getLimit();
        $limit = !$limitNode ? self::LIMIT_INFINITY : $query->getLimit()->getLimit();
        $offset = !$limitNode ? 0 : $query->getLimit()->getOffset();

        //query
        $result = $this->queryWhere($query, self::LIMIT_INFINITY, 0);

        //select/groupBy
        if ($query instanceof RqlQuery && $query->getGroupby() != null) {
            $result = $this->queryGroupBy($result, $query);
        } else {
            $result = $this->querySelect($result, $query);
        }
        //sort
        $result = $this->querySort($result, $query);
        //limit
        $result = array_slice($result, $offset, $limit == self::LIMIT_INFINITY ? null : $limit);

        //filled item unset field
        $itemFiled = [];
        foreach ($result as &$item) {
            $keys = array_keys($item);
            $diff = array_diff($keys, $itemFiled);
            $itemFiled = array_merge($itemFiled, $diff);
            $diff = array_diff($itemFiled, $keys);
            foreach ($diff as $field) {
                $item[$field] = null;
            }
        }
        return $result;
    }

    /**
     * @param Query $query
     * @param $limit
     * @param $offset
     * @return array
     */
    protected function queryWhere(Query $query, $limit, $offset)
    {
        $conditionBuilder = $this->conditionBuilder;
        $conditioon = $conditionBuilder($query->getQuery());

        $whereFunctionBody = PHP_EOL .
            '$result = ' . PHP_EOL
            . rtrim($conditioon, PHP_EOL) . ';' . PHP_EOL
            . 'return $result;';
        $whereFunction = create_function('$item', $whereFunctionBody);
        $suitableItemsNumber = 0;
        $result = [];
        foreach ($this as $value) {
            switch (true) {
                case (!($whereFunction($value))):
                    break; // skip!
                case $suitableItemsNumber < $offset:
                    $suitableItemsNumber = $suitableItemsNumber + 1;
                    break; // increment!
                case $limit <> self::LIMIT_INFINITY && $suitableItemsNumber >= ($limit + $offset):
                    return $result; //enough!
                default:
                    $result[] = $value; // write!
                    $suitableItemsNumber = $suitableItemsNumber + 1;
            }
        }
        return $result;
    }

    protected function querySort($data, Query $query)
    {
        if (empty($query->getSort())) {
            return $data;
        }
        $nextCompareLevel = '';
        $sortFields = $query->getSort()->getFields();
        foreach ($sortFields as $ordKey => $ordVal) {
            if ((int)$ordVal <> SortNode::SORT_ASC && (int)$ordVal <> SortNode::SORT_DESC) {
                throw new DataStoreException('Invalid condition: ' . $ordVal);
            }
            $cond = $ordVal == SortNode::SORT_DESC ? '<' : '>';
            $notCond = $ordVal == SortNode::SORT_ASC ? '<' : '>';
            $ordKeySafe = "'" . addslashes($ordKey) . "'";
            $prevCompareLevel = "if (!isset(\$a[$ordKeySafe])) {return 0;};" . PHP_EOL
                . "if (\$a[$ordKeySafe] $cond \$b[$ordKeySafe]) {return 1;};" . PHP_EOL
                . "if (\$a[$ordKeySafe] $notCond  \$b[$ordKeySafe]) {return -1;};" . PHP_EOL;
            $nextCompareLevel = $nextCompareLevel . $prevCompareLevel;
        }
        $sortFunctionBody = $nextCompareLevel . 'return 0;';
        $sortFunction = create_function('$a,$b', $sortFunctionBody);
        usort($data, $sortFunction);
        return $data;
    }

    protected function queryGroupBy($result, RqlQuery $query)
    {
        $groupFields = $query->getGroupby()->getFields();
        $groups = [$result];
        $groups = $this->groupBy($groups, $groupFields);

        $result = [];
        foreach ($groups as $group) {
            $data = $this->querySelect($group, $query);
            $union = [];
            foreach ($data as $item) {
                $union = array_merge($union, $item);
            }
            $result = array_merge($result, [$union]);
        }
        return $result;
    }

    protected function groupBy(array $groups, $groupFields)
    {
        $newGroup = [];
        foreach ($groups as $group) {
            foreach ($group as $item) {
                $key = '';
                foreach ($groupFields as $groupField) {
                    $key .= $item[$groupField];
                }
                $newGroup[$key][] = $item;
            }
        }
        return $newGroup;
    }

    /**
     * @param $data
     * @param Query $query
     * @return mixed
     */
    protected function querySelect($data, Query $query)
    {
        $selectNode = $query->getSelect();
        if (empty($selectNode)) {
            return $data;
        }
        $fields = array_filter($selectNode->getFields(), "is_string");
        $aggregateFunctions = array_filter($selectNode->getFields(), function ($item) {
            return $item instanceof AggregateFunctionNode;
        });

        //select fields
        $selectData = array_filter(array_map(function ($item) use ($fields) {
            return array_filter($item, function ($filed) use ($fields) {
                return in_array($filed, $fields);
            }, ARRAY_FILTER_USE_KEY);
        }, $data));

        $aggregateData = array_map(function (AggregateFunctionNode $aggregateFunction) use ($data) {
            $filed = $aggregateFunction->getField();
            $functionName = $aggregateFunction->getFunction();
            $size = count($data);
            $value = array_reduce($data, function ($carry, $item) use ($filed, $functionName, $size) {
                return $this->calculateAggregateFunction($functionName, $carry, $item[$filed], $size);
            });
            //TODO: usage decorator
            return ["$filed->$functionName" => $value];
        }, $aggregateFunctions);
        return array_merge_recursive($aggregateData, $selectData);
    }

    /**
     * @param $functionName
     * @param $carry
     * @param $currentValue
     * @param $size
     * @return float|int
     */
    protected function calculateAggregateFunction($functionName, $carry, $currentValue, $size)
    {
        switch ($functionName) {
            case "min":
                if (is_null($carry)) $carry = PHP_INT_MAX;
                return $carry > $currentValue ? $currentValue : $carry;
            case "max":
                if (is_null($carry)) $carry = PHP_INT_MIN;
                return $carry < $currentValue ? $currentValue : $carry;
            case "sum":
                if (is_null($carry)) $carry = 0;
                return $carry + $currentValue;
            case "avg":
                if (is_null($carry)) $carry = 0;
                return $carry + ($currentValue / $size);
            case "count":
                if (is_null($carry)) $carry = 0;
                return $carry + 1;
            default:
                throw new DataStoreException("Aggregate function $functionName is not supported");
        }
    }


// ** Interface "/Coutable"  **/

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    abstract public function create($itemData, $rewriteIfExist = false);

// ** Interface "/IteratorAggregate"  **/

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    abstract public function update($itemData, $createIfAbsent = false);

// ** protected  **/

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        /* $keys = $this->getKeys();
          $deletedItemsNumber = 0;
          foreach ($keys as $id) {
          $deletedNumber = $this->delete($id);
          if (is_null($deletedNumber)) {
          return null;
          }
          $deletedItemsNumber = $deletedItemsNumber + $deletedNumber;
          }
          return $deletedItemsNumber; */

        $keys = $this->getKeys();
        $deletedItemsNumber = 0;
        foreach ($keys as $id) {
            $deletedItems = $this->delete($id);
            if (is_null($deletedItems)) {
                return null;
            }
            $deletedItemsNumber++;
        }
        return $deletedItemsNumber;
    }

    /**
     * Return array of keys or empty array
     *
     * @return array array of keys or empty array
     */
    protected function getKeys()
    {
        $identifier = $this->getIdentifier();
        $query = new Query();
        $selectNode = new Node\SelectNode([$identifier]);
        $query->setSelect($selectNode);
        $queryResult = $this->query($query);
        $keysArray = [];
        foreach ($queryResult as $row) {
            $keysArray[] = $row[$identifier];
        }
        return $keysArray;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    abstract function delete($id);

    /**
     * Interface "/Coutable"
     *
     * @see /coutable
     * @return int
     */
    public function count()
    {
        $keys = $this->getKeys();
        return count($keys);
    }

    /**
     * Iterator for Interface IteratorAggregate
     *
     * @see \IteratorAggregate
     * @return \Iterator
     */
    public function getIterator()
    {
        return new DataStoreIterator($this);
    }

}
