<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore;

use phpDocumentor\Reflection\Types\Integer;
use rollun\datastore\DataStore\ConditionBuilder\ConditionBuilderAbstract;
use rollun\datastore\DataStore\Interfaces\DataStoresInterface;
use rollun\datastore\DataStore\Iterators\DataStoreIterator;
use rollun\datastore\Rql\Node\AggregateFunctionNode;
use rollun\datastore\Rql\Node\AggregateSelectNode;
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

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function query(Query $query)
    {
        $limitNode = $query->getLimit();
        $limit = !$limitNode ? self::LIMIT_INFINITY : $query->getLimit()->getLimit();
        $offset = !$limitNode ? 0 : $query->getLimit()->getOffset();
        if (isset($limitNode) && $query->getSort() !== null) {
            $data = $this->queryWhere($query, self::LIMIT_INFINITY, 0);
            $sortedData = $this->querySort($data, $query);
            $result = array_slice($sortedData, $offset, $limit == self::LIMIT_INFINITY ? null : $limit);
        } else {
            $data = $this->queryWhere($query, $limit, $offset);
            $result = $this->querySort($data, $query);
        }
        if ($query instanceof RqlQuery && $query->getGroupby() != null) {
            $result = $this->queryGroupBy($result, $query);
        } else {
            $result = $this->querySelect($result, $query);
        }
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
     * @param $result
     * @param RqlQuery $query
     */
    protected function queryWhere(Query $query, $limit, $offset)
    {
        $conditionBuilder = $this->conditionBuilder;
        $conditioon = $conditionBuilder($query->getQuery());

        $whereFunctionBody = PHP_EOL .
                '$result = ' . PHP_EOL
                . rtrim($conditioon, PHP_EOL) . ';' . PHP_EOL
                . 'return $result;';
//print_r($whereFunctionBody);
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
            if ((int) $ordVal <> SortNode::SORT_ASC && (int) $ordVal <> SortNode::SORT_DESC) {
                throw new DataStoreException('Invalid condition: ' . $ordVal);
            }
            $cond = $ordVal == SortNode::SORT_DESC ? '<' : '>';
            $notCond = $ordVal == SortNode::SORT_ASC ? '<' : '>';
            $ordKeySafe = "'". addslashes($ordKey) ."'";
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
        $selectionFields = $query->getSelect()->getFields();
        foreach ($selectionFields as &$field) {
            if (!in_array($field, $groupFields) && !($field instanceof AggregateFunctionNode)) {
                $field = new AggregateFunctionNode('count', $field);
            }
        }
        $query->setSelect(new AggregateSelectNode($selectionFields));

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

    protected function querySelect($data, Query $query)
    {
        $selectNode = $query->getSelect();
        if (empty($selectNode)) {
            return $data;
        } else {
            $resultArray = array();
            $compareArray = array();

            foreach ($selectNode->getFields() as $field) {
                if ($field instanceof AggregateFunctionNode) {
                    switch ($field->getFunction()) {
                        case 'count': {
                                $arr = [];
                                foreach ($data as $item) {
                                    if (isset($item[$field->getField()])) {
                                        $arr[] = $item[$field->getField()];
                                    }
                                }
                                $compareArray[$field->getField() . '->' . $field->getFunction()] = [count($arr)];
                                break;
                            }
                        case 'max': {
                                $firstItem = array_pop($data);
                                $max = $firstItem[$field->getField()];
                                foreach ($data as $item) {
                                    $max = $max < $item[$field->getField()] ? $item[$field->getField()] : $max;
                                }
                                array_push($data, $firstItem);
                                $compareArray[$field->getField() . '->' . $field->getFunction()] = [$max];
                                break;
                            }
                        case 'min': {
                                $firstItem = array_pop($data);
                                $min = $firstItem[$field->getField()];
                                foreach ($data as $item) {
                                    $min = $min > $item[$field->getField()] ? $item[$field->getField()] : $min;
                                }
                                array_push($data, $firstItem);
                                $compareArray[$field->getField() . '->' . $field->getFunction()] = [$min];
                                break;
                            }
                        case 'sum': {
                                $sum = 0;
                                foreach ($data as $item) {
                                    $sum += isset($item[$field->getField()]) ? $item[$field->getField()] : 0;
                                }
                                $compareArray[$field->getField() . '->' . $field->getFunction()] = [$sum];
                                break;
                            }
                        case 'avg': {
                                $sum = 0;
                                $count = 0;
                                foreach ($data as $item) {
                                    $sum += isset($item[$field->getField()]) ? $item[$field->getField()] : 0;
                                    $count += isset($item[$field->getField()]) ? 1 : 0;
                                }
                                $compareArray[$field->getField() . '->' . $field->getFunction()] = [$sum / $count];
                                break;
                            }
                    }
                } else {
                    $dataLine = [];
                    foreach ($data as $item) {
                        $dataLine[] = $item[$field];
                    }
                    $compareArray[$field] = $dataLine;
                }
            }
            $min = null;
            foreach ($compareArray as $column) {
                if (!isset($min)) {
                    $min = count($column);
                } elseif (count($column) < $min) {
                    $min = count($column);
                }
            }
            for ($i = 0; $i < $min; ++$i) {
                $item = [];
                foreach ($compareArray as $fieldName => $column) {
                    $item[$fieldName] = $column[$i];
                }
                $resultArray[] = $item;
            }
            return $resultArray;
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
